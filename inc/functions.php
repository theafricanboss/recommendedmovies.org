<?php
require_once '././vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(dirname(__DIR__, 2), '.env');
$dotenv->safeLoad();
define('TMDB_API_KEY', $_ENV['TMDB_API_KEY'] ?? '');
define('TMDB_URL', "https://api.themoviedb.org/3");
// TMDB's documented `secure_base_url` (see /configuration) — the only image
// host their API guarantees. media.themoviedb.org is not part of the public
// API; it 301-redirects to this exact host, so using it directly saves a round
// trip and matches https://developer.themoviedb.org/docs/image-basics.
define('TMDB_IMG', "https://image.tmdb.org/t/p");
define('TMDB_CACHE_DIR', sys_get_temp_dir() . '/recommended_movies_cache');
define('TMDB_TIMEOUT', 10); // seconds allowed per TMDB request

if (empty(TMDB_API_KEY)) {
  echo_error("TMDB_API_KEY is not set", 500);
}

function echo_error($text, $status = 404) {
    http_response_code($status);
    $message = htmlspecialchars($text);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Nothing found | Recommended Movies</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&display=swap">
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<main class="state">
  <div class="state__icon" aria-hidden="true">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/><path d="M8.5 11h5"/></svg>
  </div>
  <h1 class="state__title">We couldn't build that list</h1>
  <p class="state__text">$message</p>
  <form class="searchbar searchbar--lg" method="get" action="/search.php" role="search">
    <label class="sr-only" for="state-q">Title</label>
    <input id="state-q" class="searchbar__input" type="text" name="n" placeholder="Try another title&hellip;" autocomplete="off" required>
    <div class="segmented" role="radiogroup" aria-label="Type">
      <input id="state-movie" type="radio" name="t" value="movie" checked>
      <label for="state-movie">Movies</label>
      <input id="state-tv" type="radio" name="t" value="tv">
      <label for="state-tv">TV</label>
    </div>
    <button class="btn btn--accent" type="submit">Search</button>
  </form>
  <p class="state__meta"><a href="/">Back to home</a> &middot; <a href="mailto:info@recommendedmovies.org">Contact support</a></p>
</main>
</body>
</html>
HTML;
    die();
}

function secure_input($text) {
    $text = trim($text);
    $text = stripslashes($text);
    $text = htmlspecialchars($text);
    return $text;
}

// ---------------------------------------------------------------------------
// TMDB HTTP layer
//
// Every call goes through tmdb_fetch()/tmdb_fetch_multi(), which:
//  - always decodes to an associative array (the old code mixed json_decode()
//    with and without `true`, which caused array/object bugs downstream)
//  - applies a connect/total timeout instead of relying on PHP defaults
//  - short-circuits on non-2xx / malformed responses instead of letting a
//    bad TMDB response crash the page (count() on null, etc.)
//  - caches responses to disk so repeat page loads for the same title, and
//    slow-changing lists like "popular"/"top rated", don't hit the network
//  - can fire several requests concurrently via curl_multi, which is the
//    main latency win: the old code fetched each cast member's credits one
//    request at a time (fully sequential round trips).
// ---------------------------------------------------------------------------

function tmdb_cache_path($key) {
    if (!is_dir(TMDB_CACHE_DIR)) {
        @mkdir(TMDB_CACHE_DIR, 0777, true);
    }
    return TMDB_CACHE_DIR . '/' . md5($key) . '.json';
}

function tmdb_cache_get($key, $ttl) {
    if ($ttl <= 0) return null;
    $path = tmdb_cache_path($key);
    if (!is_file($path) || (time() - filemtime($path)) > $ttl) return null;
    $raw = @file_get_contents($path);
    if ($raw === false || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

function tmdb_cache_set($key, $data) {
    @file_put_contents(tmdb_cache_path($key), json_encode($data));
}

function tmdb_build_url($endpoint) {
    $sep = (strpos($endpoint, '?') === false) ? '?' : '&';
    return TMDB_URL . $endpoint . $sep . 'api_key=' . TMDB_API_KEY;
}

/**
 * Fetch a single TMDB endpoint (e.g. "/movie/603/similar"), cached for $ttl seconds.
 * Returns an associative array, or null on failure.
 */
function tmdb_fetch($endpoint, $ttl = 3600) {
    $result = tmdb_fetch_multi(['_' => $endpoint], $ttl);
    return $result['_'];
}

/**
 * Fetch several TMDB endpoints concurrently.
 *
 * $endpoints is [key => endpoint]. $ttl is either a single seconds value
 * applied to every endpoint, or a [key => seconds] map for per-endpoint
 * cache lifetimes. Returns [key => decoded array|null], preserving keys.
 * Cache hits are served without touching the network; only misses are
 * sent out, in parallel, via curl_multi.
 */
function tmdb_fetch_multi(array $endpoints, $ttl = 3600) {
    $results = [];
    $pending = [];

    foreach ($endpoints as $key => $endpoint) {
        $entry_ttl = is_array($ttl) ? ($ttl[$key] ?? 3600) : $ttl;
        $cached = tmdb_cache_get($endpoint, $entry_ttl);
        if ($cached !== null) {
            $results[$key] = $cached;
        } else {
            $pending[$key] = $endpoint;
        }
    }

    if (empty($pending)) return $results;

    $mh = curl_multi_init();
    $handles = [];

    foreach ($pending as $key => $endpoint) {
        $ch = curl_init(tmdb_build_url($endpoint));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => TMDB_TIMEOUT,
            CURLOPT_TIMEOUT => TMDB_TIMEOUT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }

    $active = null;
    do {
        $status = curl_multi_exec($mh, $active);
        if ($active) curl_multi_select($mh);
    } while ($active && $status == CURLM_OK);

    foreach ($handles as $key => $ch) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = curl_multi_getcontent($ch);
        $data = null;
        if (!curl_errno($ch) && $http_code >= 200 && $http_code < 300 && $body) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $data = $decoded;
                tmdb_cache_set($pending[$key], $data);
            }
        }
        $results[$key] = $data;
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);
    return $results;
}

// ---------------------------------------------------------------------------
// Presentation helpers
//
// TMDB items arrive in three shapes (search results, detail objects, and
// person credits), each naming the same field differently. These normalise
// them so templates never have to guess.
// ---------------------------------------------------------------------------

function tmdb_title($item) {
    return $item['title']
        ?? $item['name']
        ?? $item['original_title']
        ?? $item['original_name']
        ?? '';
}

function tmdb_overview($item) {
    return $item['overview'] ?? ($item['description'] ?? '');
}

function tmdb_date($item) {
    return $item['release_date'] ?? ($item['first_air_date'] ?? '');
}

function tmdb_year($item) {
    $date = tmdb_date($item);
    return $date ? substr($date, 0, 4) : '';
}

/** Rounded 0-10 score, or '' when a title has no meaningful votes yet. */
function tmdb_rating($item) {
    $score = $item['vote_average'] ?? 0;
    $votes = $item['vote_count'] ?? 0;
    if (!$score || $votes < 10) return '';
    return number_format((float) $score, 1);
}

/**
 * Is TMDB's image CDN actually reachable from this server?
 *
 * Any HTTP response at all proves the route works — even a 403 — so this
 * checks for the absence of a connection error rather than a 200. The answer
 * is cached: a working route is rechecked hourly, a broken one every few
 * minutes so the site recovers quickly once the block lifts.
 */
function tmdb_images_reachable() {
    static $reachable = null;
    if ($reachable !== null) return $reachable;
    if (TMDB_IMG_PROXY === '') return $reachable = true;

    $cached = tmdb_cache_get('__tmdb_img_reachable_ok', 3600);
    if ($cached !== null) return $reachable = true;
    $cached = tmdb_cache_get('__tmdb_img_reachable_fail', 300);
    if ($cached !== null) return $reachable = false;

    $ch = curl_init(TMDB_IMG . '/w92/');
    curl_setopt_array($ch, [
        CURLOPT_NOBODY => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 3,
    ]);
    curl_exec($ch);
    $reachable = curl_errno($ch) === 0;
    curl_close($ch);

    tmdb_cache_set($reachable ? '__tmdb_img_reachable_ok' : '__tmdb_img_reachable_fail', ['ok' => $reachable]);
    return $reachable;
}

/** Build an image URL on whichever route currently works. */
function tmdb_img($size, $path) {
    $base = tmdb_images_reachable() ? TMDB_IMG : TMDB_IMG_PROXY;
    return $base . "/$size$path";
}

/**
 * Poster artwork for a listing card.
 *
 * Sizes below are exactly TMDB's documented `poster_sizes` /
 * `backdrop_sizes` from /configuration — w92, w154, w185, w342, w500, w780,
 * original for posters; w300, w780, w1280, original for backdrops. An earlier
 * version of this used an undocumented `w{W}_and_h{H}_face` crop mode that
 * isn't part of the public API and isn't guaranteed to work; it's gone.
 *
 * Cards are portrait (2:3), so a poster is what belongs in them — the old
 * code fed them 16:9 `backdrop_path` images, which object-fit then cropped
 * down to a sliver of the middle of the frame. Posters are used first, a
 * backdrop only as a stand-in, and the bundled placeholder as a last resort.
 * Two renditions are offered so retina screens get the sharper file without
 * making everyone else pay for it.
 */
function tmdb_poster($item) {
    if (!empty($item['poster_path'])) {
        $path = $item['poster_path'];
        return [
            'src' => tmdb_img('w342', $path),
            'srcset' => tmdb_img('w342', $path) . ' 342w, ' . tmdb_img('w500', $path) . ' 500w',
            'sizes' => '(max-width: 640px) 45vw, (max-width: 1200px) 22vw, 200px',
        ];
    }
    if (!empty($item['backdrop_path'])) {
        return [
            'src' => tmdb_img('w780', $item['backdrop_path']),
            'srcset' => '',
            'sizes' => '',
        ];
    }
    // No artwork at all: the card renders no <img>, and the styled empty state
    // behind it shows through instead of a stretched stand-in image.
    return ['src' => '', 'srcset' => '', 'sizes' => ''];
}

/** Wide artwork for the page hero; falls back to the poster, then nothing. */
function tmdb_backdrop($item) {
    if (!empty($item['backdrop_path'])) return tmdb_img('w1280', $item['backdrop_path']);
    if (!empty($item['poster_path'])) return tmdb_img('w780', $item['poster_path']);
    return '';
}

/** True when a title has real artwork — used to keep empty cards out of grids. */
function tmdb_has_artwork($item) {
    return !empty($item['poster_path']) || !empty($item['backdrop_path']);
}

/** [genre id => name] for "movie" or "tv", cached for a week. */
function find_genres($type) {
    $base = $type === 'tv' ? '/genre/tv/list' : '/genre/movie/list';
    $data = tmdb_fetch($base, 604800);
    $map = [];
    foreach ($data['genres'] ?? [] as $genre) {
        if (isset($genre['id'], $genre['name'])) $map[$genre['id']] = $genre['name'];
    }
    return $map;
}

/** Genre names for an item, accepting either `genres` objects or `genre_ids`. */
function tmdb_genre_names($item, array $genre_map = [], $limit = 3) {
    $names = [];
    foreach ($item['genres'] ?? [] as $genre) {
        if (!empty($genre['name'])) $names[] = $genre['name'];
    }
    if (!$names) {
        foreach ($item['genre_ids'] ?? [] as $id) {
            if (isset($genre_map[$id])) $names[] = $genre_map[$id];
        }
    }
    return array_slice($names, 0, $limit);
}

// ---------------------------------------------------------------------------
// Lookups
// ---------------------------------------------------------------------------

function find_movies($movie_title) {
    $data = tmdb_fetch("/search/movie?query=" . urlencode($movie_title), 3600);
    return $data['results'] ?? [];
}

function find_shows($show_name) {
    $data = tmdb_fetch("/search/tv?query=" . urlencode($show_name), 3600);
    return $data['results'] ?? [];
}

/**
 * Pick which search result the query actually meant.
 *
 * TMDB orders search by popularity, which hands "Toy Story" to the unreleased
 * Toy Story 5 and "Dune" to whichever entry is trending. An exact title match
 * wins, an established title beats an announced one, and popularity only
 * breaks ties between genuine candidates.
 */
function pick_search_match(array $results, $query) {
    if (!$results) return null;

    $normalise = fn($text) => preg_replace('/[^a-z0-9]+/', ' ', strtolower(trim((string) $text)));
    $target = $normalise($query);
    $today = date('Y-m-d');
    $best = null;
    $best_score = -INF;

    foreach ($results as $item) {
        $title = $normalise(tmdb_title($item));
        $original = $normalise($item['original_title'] ?? ($item['original_name'] ?? ''));
        $score = 0.0;

        if ($title === $target || $original === $target) {
            $score += 10;
        } elseif (strpos($title, $target) === 0) {
            $score += 4;                       // "the matrix" for "the matrix re…"
        } elseif (strpos($title, $target) !== false) {
            $score += 2;
        }

        $date = tmdb_date($item);
        if (!$date || $date > $today) $score -= 3;   // unreleased or undated
        $score += min(log10(1 + (float) ($item['vote_count'] ?? 0)), 4);
        $score += min(log10(1 + (float) ($item['popularity'] ?? 0)) / 2, 1.5);
        if (tmdb_has_artwork($item)) $score += 0.5;

        if ($score > $best_score) {
            $best_score = $score;
            $best = $item;
        }
    }

    return $best ?? $results[0];
}

function find_movie_url($id, $locale) {
    if (empty($id)) return '';
    $data = tmdb_fetch("/movie/$id/watch/providers", 86400);
    return $data['results'][$locale]['link'] ?? '';
}

function find_show_url($id, $locale) {
    if (empty($id)) return '';
    $data = tmdb_fetch("/tv/$id/watch/providers", 86400);
    return $data['results'][$locale]['link'] ?? '';
}

require_once __DIR__ . '/concepts.php';

// ---------------------------------------------------------------------------
// Thematic engine — "shows and films built on the same idea"
//
// Runs across both media types on purpose: a prison-break show should be able
// to surface a heist film. See inc/concepts.php for the reasoning behind the
// keyword-bucket and concept layers.
// ---------------------------------------------------------------------------

/** TMDB's mood tags describe a vibe, not a subject; they only add noise here. */
function thematic_keyword_stoplist() {
    return array_flip([
        'excited', 'shocking', 'suspenseful', 'awestruck', 'mischievous', 'gritty',
        'quirky', 'feel-good', 'heartwarming', 'emotional', 'inspiring', 'tense',
        'funny', 'sad', 'scary', 'romantic', 'nostalgic', 'effects from the past',
        'woman director', 'duringcreditsstinger', 'aftercreditsstinger', 'based on novel or book',
        'new york city', 'los angeles', 'london', 'paris', 'tokyo', 'las vegas', 'chicago',
        'san francisco', 'hong kong', 'berlin', 'moscow', 'rome', 'miami', 'boston',
        'seattle', 'texas', 'california', 'florida', 'usa', 'england', 'japan',
    ]);
}

/**
 * Where a story is set says nothing about what it is about. TMDB writes
 * places as "city, region", so a comma is a reliable tell — without this,
 * Now You See Me recommends NYPD Blue for sharing "new york city".
 */
function is_place_keyword($name) {
    return strpos($name, ',') !== false;
}

/**
 * Endpoints that turn each of the subject's keywords into its own bucket of
 * titles, in both media types. Returned as [key => endpoint] so the caller can
 * fold them into an existing parallel batch.
 */
function thematic_discovery_endpoints($subject, array $keywords, $limit = 8) {
    $stoplist = thematic_keyword_stoplist();
    $endpoints = [];
    $wanted = [];

    // The subject's own tags, most specific first: a two-word tag such as
    // "escaped prisoner" pins the idea down far better than "prison" does,
    // and TMDB lists the broad ones first, so plain order is the wrong order.
    $tags = [];
    foreach ($keywords as $keyword) {
        if (empty($keyword['id']) || empty($keyword['name'])) continue;
        if (isset($stoplist[strtolower($keyword['name'])])) continue;
        if (is_place_keyword($keyword['name'])) continue;
        $tags[] = $keyword;
    }
    usort($tags, fn($a, $b) => substr_count($b['name'], ' ') <=> substr_count($a['name'], ' '));
    foreach (array_slice($tags, 0, $limit) as $keyword) $wanted[(int) $keyword['id']] = true;

    // Then the bridge: anchors of the concepts neighbouring this title's own.
    $profile = concept_profile(array_merge(
        text_tokens(implode(' ', array_column($keywords, 'name'))),
        text_tokens(tmdb_overview($subject))
    ));
    $anchors = concept_anchor_keywords();
    foreach (array_keys(concept_neighbours($profile, 4)) as $concept) {
        foreach (array_slice($anchors[$concept] ?? [], 0, 2) as $id) $wanted[(int) $id] = true;
    }

    foreach (array_keys($wanted) as $id) {
        foreach (['movie', 'tv'] as $media) {
            $endpoints["kw:$media:$id"] = "/discover/$media?with_keywords=$id"
                . "&sort_by=popularity.desc&vote_count.gte=20&page=1";
        }
    }
    return $endpoints;
}

/**
 * Rank thematically similar titles across both media types.
 *
 * $buckets  [keyword id => ['name' => …, 'items' => […], 'total' => int]]
 * $exclude  ids already shown elsewhere on the page
 */
function rank_thematic_matches(array $subject, $media_type, array $buckets, array $subject_keywords, array $exclude = [], $limit = 18) {
    $subject_id = $subject['id'] ?? null;
    $subject_genres = array_values(array_filter(array_map(
        fn($g) => is_array($g) ? ($g['id'] ?? null) : $g,
        $subject['genres'] ?? ($subject['genre_ids'] ?? [])
    )));

    // The subject's own fingerprint: its keyword names plus its synopsis.
    $subject_tokens = array_merge(
        text_tokens(implode(' ', array_column($subject_keywords, 'name'))),
        text_tokens(tmdb_overview($subject))
    );
    $subject_profile = concept_profile($subject_tokens);

    // Pool every bucket, remembering which keywords each title turned up under.
    $pool = [];
    foreach ($buckets as $bucket) {
        $total = max((int) ($bucket['total'] ?? 0), 1);
        // A keyword on 60 titles is far more telling than one on 6,000.
        $idf = log(250000 / $total);
        if ($idf < 0) $idf = 0;

        $position = 0;
        foreach ($bucket['items'] as $item) {
            $id = $item['id'] ?? null;
            $position++;
            if (!$id || $id == $subject_id || isset($exclude[$id])) continue;
            if (!empty($item['adult']) || !tmdb_has_artwork($item)) continue;
            if (!tmdb_title($item) || !tmdb_overview($item)) continue;

            if (!isset($pool[$id])) {
                $item['media_type'] = $bucket['media'];
                $pool[$id] = ['item' => $item, 'keyword_score' => 0.0, 'shared' => []];
            }
            // Deeper results in a bucket are less characteristic of it.
            $pool[$id]['keyword_score'] += $idf * (1 - 0.4 * min($position / 20, 1));
            $pool[$id]['shared'][] = $bucket['name'];
        }
    }

    if (!$pool) return [];

    // Document frequencies over the pool, so common synopsis words carry less.
    $document_frequency = [];
    foreach ($pool as $id => $entry) {
        $tokens = array_unique(text_tokens(tmdb_overview($entry['item'])));
        $pool[$id]['tokens'] = $tokens;
        foreach ($tokens as $token) {
            $document_frequency[$token] = ($document_frequency[$token] ?? 0) + 1;
        }
    }
    $documents = count($pool);

    $labels = concept_labels();
    $matches = [];

    foreach ($pool as $id => $entry) {
        $item = $entry['item'];

        $candidate_profile = concept_profile(array_merge(
            $entry['tokens'],
            text_tokens(implode(' ', $entry['shared']))
        ));

        $concept_score = concept_soft_cosine($subject_profile, $candidate_profile);
        $text_score = text_overlap_score($subject_tokens, $entry['tokens'], $document_frequency, $documents);
        $shared = array_values(array_unique($entry['shared']));

        $candidate_genres = $item['genre_ids'] ?? [];

        $score = 0.0;
        $score += 1.9 * min($entry['keyword_score'] / 12, 1.6);  // keyword evidence
        $score += 3.4 * $concept_score;                          // same idea
        $score += 2.1 * $text_score;                             // same synopsis language
        $score += 0.9 * (count($shared) > 1 ? min(count($shared) - 1, 3) / 3 : 0); // multi-tag agreement

        // Genre agreement is a light touch here: movie and TV vocabularies
        // differ, and the point of this shelf is to cross that line anyway.
        if ($subject_genres && $candidate_genres) {
            $union = count(array_unique(array_merge($subject_genres, $candidate_genres)));
            if ($union > 0) {
                $score += 1.1 * (count(array_intersect($subject_genres, $candidate_genres)) / $union);
            }
        }
        $score -= min(1.5 * genre_mode_mismatch($subject_genres, $candidate_genres), 3.0);

        // Among titles that are equally on-theme, recommend the good ones.
        $score += 1.5 * (tmdb_weighted_rating($item) / 10);
        $score += 0.5 * min(log10(1 + (float) ($item['popularity'] ?? 0)) / 2.5, 1);
        if ((int) ($item['vote_count'] ?? 0) < 60) $score -= 0.5;

        // Explain the connection the way a person would. Shared tags say it
        // best; the concept label is the fallback for a bridged match, where
        // the two titles share an idea but not a single tag.
        $reasons = [];
        if ($shared) {
            $reasons[] = 'Also tagged ' . implode(', ', array_slice($shared, 0, 3));
        } else {
            $overlap = [];
            foreach ($subject_profile as $concept => $weight) {
                if (isset($candidate_profile[$concept])) {
                    $overlap[$concept] = $weight * $candidate_profile[$concept];
                }
            }
            arsort($overlap);
            $top_concept = key($overlap);
            if ($top_concept && isset($labels[$top_concept])) {
                $reasons[] = 'Same idea: ' . $labels[$top_concept];
            }
        }

        $item['match_score_raw'] = $score;
        $item['match_reasons'] = array_slice($reasons, 0, 1);
        $matches[] = $item;
    }

    usort($matches, fn($a, $b) => $b['match_score_raw'] <=> $a['match_score_raw']);
    $matches = array_slice($matches, 0, $limit);

    $best = $matches[0]['match_score_raw'] ?? 0;
    foreach ($matches as &$match) {
        $match['match_score'] = $best > 0 ? (int) round(100 * $match['match_score_raw'] / $best) : 0;
        unset($match['match_score_raw']);
    }
    unset($match);

    return $matches;
}

// ---------------------------------------------------------------------------
// Match engine
//
// No single TMDB endpoint answers "what is closest to this?" well. `/similar`
// is keyword-driven and drifts; `/recommendations` reflects viewing behaviour
// and misses obscure-but-perfect matches; a shared cast alone puts an actor's
// unrelated comedy next to their thriller.
//
// So candidates are gathered from six independent signals and scored together.
// The core idea is consensus: a title that shows up under several unrelated
// signals — same franchise AND same director AND shared themes — is far more
// likely to be a genuinely close match than whatever happens to top one list.
// Intrinsic similarity (genre overlap, era, language) and audience quality
// then break the remaining ties.
// ---------------------------------------------------------------------------

/**
 * IMDb-style weighted rating. A 10.0 from nine voters should not outrank a 8.4
 * from fifty thousand, so scores are pulled toward the mean until the vote
 * count earns the title its full rating.
 */
function tmdb_weighted_rating($item, $m = 800, $mean = 6.7) {
    $votes = (float) ($item['vote_count'] ?? 0);
    $score = (float) ($item['vote_average'] ?? 0);
    if ($votes <= 0 || $score <= 0) return 0.0;
    return ($votes / ($votes + $m)) * $score + ($m / ($votes + $m)) * $mean;
}

/**
 * How badly two titles disagree on "mode" genres — animation, documentary,
 * family, kids. These describe how a thing is made and who it is for rather
 * than what it is about, so crossing the line breaks similarity harder than a
 * shared subject can repair. Counted in both directions.
 *
 * Movie and TV genre vocabularies differ for action and sci-fi, but the mode
 * ids are common to both, so this is safe to use across media types.
 */
function genre_mode_mismatch(array $subject_genres, array $candidate_genres) {
    $modes = [16, 99, 10751, 10762, 10763, 10764, 10767, 10770];
    $subject_modes = array_intersect($modes, $subject_genres);
    $candidate_modes = array_intersect($modes, $candidate_genres);
    return count(array_diff($candidate_modes, $subject_modes))
        + count(array_diff($subject_modes, $candidate_modes));
}

/** Relative weight of each candidate source. */
function match_source_weights() {
    return [
        'collection' => 7.0,   // same franchise — as close as titles get
        'recommendations' => 3.2, // TMDB's behavioural engine
        'keywords' => 2.6,     // shares the subject's themes/tropes
        'similar' => 2.0,      // TMDB's keyword/genre similarity
        'director' => 2.4,     // same director or creator
        'cast' => 1.4,         // shares one of the leads
        'genre_era' => 1.0,    // well-regarded, same genre, same era
    ];
}

/**
 * Score and rank pooled candidates.
 *
 * $pools    [source => items]
 * $signals  subject facts: genre_ids, year, language, cast_share (id => shared
 *           lead count), director_ids (candidate ids the director worked on)
 */
function rank_best_matches(array $subject, $media_type, array $pools, array $signals, $limit = 10) {
    $weights = match_source_weights();
    $subject_id = $subject['id'] ?? null;
    $genre_ids = $signals['genre_ids'] ?? [];
    $subject_year = (int) ($signals['year'] ?? 0);
    $language = $signals['language'] ?? '';
    $cast_share = $signals['cast_share'] ?? [];
    $director_ids = $signals['director_ids'] ?? [];

    $candidates = [];

    foreach ($pools as $source => $items) {
        $count = max(count($items), 1);
        $position = 0;
        foreach ($items as $item) {
            $id = $item['id'] ?? null;
            $position++;
            if (!$id || $id == $subject_id) continue;
            if (!empty($item['adult'])) continue;
            if (!tmdb_has_artwork($item)) continue;
            // Person credits carry a media_type; everything else is already
            // scoped to the right endpoint.
            if (isset($item['media_type']) && $item['media_type'] !== $media_type) continue;

            if (!isset($candidates[$id])) {
                $candidates[$id] = ['item' => $item, 'score' => 0.0, 'sources' => []];
            } elseif (count($item) > count($candidates[$id]['item'])) {
                // Keep whichever payload carries the most detail.
                $candidates[$id]['item'] = $item;
            }

            // Rank decay: being 1st in a list means more than being 20th.
            $decay = 1 - 0.5 * (($position - 1) / $count);
            $candidates[$id]['score'] += ($weights[$source] ?? 1.0) * $decay;
            $candidates[$id]['sources'][$source] = true;
        }
    }

    foreach ($candidates as $id => &$entry) {
        $item = $entry['item'];
        $reasons = [];

        // Consensus across independent signals is the strongest evidence we have.
        $source_count = count($entry['sources']);
        $entry['score'] += 1.6 * ($source_count - 1);

        // Genre overlap (Jaccard) — how much of the subject's DNA it shares.
        // Sharing nothing is itself informative: an animated family film is not
        // a close match for a heist thriller however many keywords they share,
        // so a clean miss is penalised rather than merely unrewarded.
        $candidate_genres = $item['genre_ids'] ?? array_column($item['genres'] ?? [], 'id');
        if ($genre_ids && $candidate_genres) {
            $shared = count(array_intersect($genre_ids, $candidate_genres));
            $union = count(array_unique(array_merge($genre_ids, $candidate_genres)));
            if ($union > 0) $entry['score'] += 2.2 * ($shared / $union);
            if ($shared === 0) $entry['score'] -= 1.8;
        }

        // "Mode" genres describe how a title is made and who it is for rather
        // than what it is about: animation, family, documentary, talk show.
        // Crossing that line breaks similarity harder than a shared theme can
        // repair — Toy Story is not a close match for Inception on the
        // strength of both being "Adventure" — so mismatches are penalised in
        // both directions.
        $mode_genres = [16, 99, 10751, 10762, 10763, 10764, 10767, 10770];
        $subject_modes = array_intersect($mode_genres, $genre_ids);
        $candidate_modes = array_intersect($mode_genres, $candidate_genres);
        $mode_mismatch = count(array_diff($candidate_modes, $subject_modes))
            + count(array_diff($subject_modes, $candidate_modes));
        if ($mode_mismatch > 0) $entry['score'] -= min(1.7 * $mode_mismatch, 3.4);

        // Shared leads.
        $shared_cast = $cast_share[$id] ?? 0;
        if ($shared_cast > 0) {
            $entry['score'] += 0.9 * min($shared_cast, 3);
            $reasons[] = $shared_cast === 1 ? 'Shares a lead actor' : "Shares $shared_cast cast members";
        }

        if (isset($director_ids[$id])) $reasons[] = 'Same director';
        if (isset($entry['sources']['collection'])) array_unshift($reasons, 'Same franchise');
        if (isset($entry['sources']['keywords'])) $reasons[] = 'Shared themes';
        if (isset($entry['sources']['recommendations'])) $reasons[] = 'Viewers also picked';
        if (isset($entry['sources']['similar'])) $reasons[] = 'TMDB similar';

        // Audience quality, normalised across the 0-10 scale.
        $entry['score'] += 1.4 * (tmdb_weighted_rating($item) / 10);

        // Recognisability, log-scaled so a blockbuster can't dominate outright.
        $entry['score'] += 0.5 * min(log10(1 + (float) ($item['popularity'] ?? 0)) / 2.5, 1);

        // Era proximity: contemporaries feel closer than a remake 40 years on.
        $year = (int) tmdb_year($item);
        if ($subject_year && $year) {
            $entry['score'] += 0.8 * exp(-pow(($year - $subject_year) / 14, 2));
        }

        // Same production language usually means a comparable style of film.
        if ($language && ($item['original_language'] ?? '') === $language) {
            $entry['score'] += 0.4;
        }

        // Titles with almost no votes are noise, not discoveries.
        if ((int) ($item['vote_count'] ?? 0) < 50) $entry['score'] -= 1.2;

        $entry['reasons'] = array_slice($reasons, 0, 2);
    }
    unset($entry);

    uasort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
    $top = array_slice($candidates, 0, $limit, true);
    if (!$top) return [];

    // Express the ranking as closeness relative to the best match.
    $best = max(array_column($top, 'score'));
    $matches = [];
    foreach ($top as $entry) {
        $item = $entry['item'];
        $item['match_score'] = $best > 0 ? (int) round(100 * $entry['score'] / $best) : 0;
        $item['match_reasons'] = $entry['reasons'];
        $matches[] = $item;
    }
    return $matches;
}

// ---------------------------------------------------------------------------
// Recommendations
//
// Three shelves come out of here plus the ranked best matches:
//  - best_matches: the engine above, top 10
//  - similar_cast_genre: shares cast and at least one genre
//  - similar: TMDB's own "similar" endpoint
//  - related: everything gathered from the main cast's filmography
//
// Everything the page needs is fetched in two parallel rounds: the first for
// facts that only need the subject id, the second for the person filmographies
// and discovery queries that depend on the first round's answers.
// ---------------------------------------------------------------------------

function build_recommendations($subject, $locale, $media_type) {
    if (!is_array($subject) || empty($subject['id'])) return null;

    $id = $subject['id'];
    $title = $subject['title'] ?? ($subject['name'] ?? ($subject['original_title'] ?? ($subject['original_name'] ?? '')));
    $description = $subject['overview'] ?? ($subject['description'] ?? '');
    $imdb = !empty($subject['imdb_id']) ? "https://imdb.com/title/{$subject['imdb_id']}" : "#";
    $genres = $subject['genres'] ?? ($subject['genre_ids'] ?? []);
    $genre_ids = array_values(array_filter(
        array_map(fn($g) => is_array($g) ? ($g['id'] ?? null) : $g, $genres),
        fn($g) => $g !== null
    ));
    $subject_year = (int) tmdb_year($subject);
    $language = $subject['original_language'] ?? '';

    $base = $media_type === 'tv' ? '/tv' : '/movie';

    // --- Round 1: everything keyed only on the subject id --------------------
    $round1 = [
        'similar' => "$base/$id/similar",
        'recommendations' => "$base/$id/recommendations",
        'credits' => "$base/$id/credits",
        'keywords' => "$base/$id/keywords",
        'providers' => "$base/$id/watch/providers",
        'genres' => $media_type === 'tv' ? '/genre/tv/list' : '/genre/movie/list',
    ];
    $collection_id = $subject['belongs_to_collection']['id'] ?? null;
    if ($collection_id) $round1['collection'] = "/collection/$collection_id";

    $primary = tmdb_fetch_multi($round1, [
        'similar' => 21600, 'recommendations' => 21600, 'credits' => 86400,
        'keywords' => 604800, 'providers' => 86400, 'genres' => 604800,
        'collection' => 604800,
    ]);

    $similar = array_slice($primary['similar']['results'] ?? [], 0, 20);
    $recommended = array_slice($primary['recommendations']['results'] ?? [], 0, 20);
    $cast = array_slice($primary['credits']['cast'] ?? [], 0, 5);
    $collection_parts = $primary['collection']['parts'] ?? [];
    // Movies return `keywords`, shows return `results`.
    $keywords = $primary['keywords']['keywords'] ?? ($primary['keywords']['results'] ?? []);

    $genre_map = [];
    foreach ($primary['genres']['genres'] ?? [] as $genre) {
        if (isset($genre['id'], $genre['name'])) $genre_map[$genre['id']] = $genre['name'];
    }
    $watch_url = $primary['providers']['results'][$locale]['link'] ?? '';

    // Whoever shaped the thing: a film's director, a show's creator.
    $director = null;
    foreach ($primary['credits']['crew'] ?? [] as $member) {
        if (($member['job'] ?? '') === 'Director' && !empty($member['id'])) { $director = $member; break; }
    }
    if (!$director && !empty($subject['created_by'][0]['id'])) $director = $subject['created_by'][0];

    // --- Round 2: filmographies and discovery, all in parallel ---------------
    $round2 = [];
    foreach ($cast as $member) {
        if (!empty($member['id'])) $round2["cast:{$member['id']}"] = "/person/{$member['id']}/combined_credits";
    }
    if ($director) $round2["director:{$director['id']}"] = "/person/{$director['id']}/combined_credits";

    $keyword_ids = array_slice(array_filter(array_column($keywords, 'id')), 0, 5);
    if ($keyword_ids) {
        $round2['discover_keywords'] = "/discover/$media_type?with_keywords=" . implode('%7C', $keyword_ids)
            . "&sort_by=vote_count.desc&vote_count.gte=100&page=1";
    }
    if ($genre_ids) {
        $date_field = $media_type === 'tv' ? 'first_air_date' : 'primary_release_date';
        $era = "";
        if ($subject_year) {
            $era = "&$date_field.gte=" . ($subject_year - 12) . "-01-01&$date_field.lte=" . ($subject_year + 12) . "-12-31";
        }
        $round2['discover_genre'] = "/discover/$media_type?with_genres=" . implode(',', array_slice($genre_ids, 0, 2))
            . "&sort_by=vote_average.desc&vote_count.gte=500$era&page=1";
    }

    // One bucket per keyword, in both media types, folded into the same
    // parallel round so the thematic shelf costs no extra round trip.
    $round2 += thematic_discovery_endpoints($subject, $keywords, 10);

    $fetched = tmdb_fetch_multi($round2, 86400);

    // --- Cast / director filmographies --------------------------------------
    $related = [];
    $similar_cast_genre = [];
    $cast_pool = [];
    $cast_share = [];
    $director_ids = [];
    $seen = [$id => true];

    foreach ($fetched as $key => $data) {
        if (!$data || strpos($key, 'cast:') !== 0) continue;

        $credits = array_merge($data['cast'] ?? [], $data['crew'] ?? []);
        $credits = array_values(array_filter($credits, fn($c) => ($c['media_type'] ?? null) === $media_type));
        usort($credits, fn($a, $b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));

        // Count shared leads across the whole filmography, but only the best
        // known work of each actor earns a slot in the visible shelves.
        foreach ($credits as $credit) {
            if (!empty($credit['id']) && $credit['id'] != $id) {
                $cast_share[$credit['id']] = ($cast_share[$credit['id']] ?? 0) + 1;
            }
        }

        foreach (array_slice($credits, 0, 10) as $candidate) {
            $cid = $candidate['id'] ?? null;
            if (!$cid || isset($seen[$cid])) continue;
            // Deep cuts of a filmography often have no artwork at all and only
            // render as empty placeholder cards, so they're not worth a slot.
            if (!tmdb_has_artwork($candidate)) continue;
            $seen[$cid] = true;

            $related[] = $candidate;
            $cast_pool[] = $candidate;

            if (array_intersect($genre_ids, $candidate['genre_ids'] ?? [])) {
                $similar_cast_genre[] = $candidate;
            }
        }
    }

    $director_pool = [];
    foreach ($fetched as $key => $data) {
        if (!$data || strpos($key, 'director:') !== 0) continue;
        $credits = array_merge($data['crew'] ?? [], $data['cast'] ?? []);
        foreach ($credits as $credit) {
            if (($credit['media_type'] ?? null) !== $media_type) continue;
            if (empty($credit['id']) || $credit['id'] == $id) continue;
            if (isset($director_ids[$credit['id']])) continue;
            $director_ids[$credit['id']] = true;
            $director_pool[] = $credit;
        }
        usort($director_pool, fn($a, $b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));
    }

    usort($related, fn($a, $b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));
    usort($similar_cast_genre, fn($a, $b) => ($b['popularity'] ?? 0) <=> ($a['popularity'] ?? 0));

    // "Same cast & genre" is a subset of the cast filmography, so showing both
    // in popularity order opened the two shelves with identical rows. The
    // broader shelf keeps only what the tighter one didn't already show.
    $shown = array_flip(array_column($similar_cast_genre, 'id'));
    $related = array_values(array_filter($related, fn($item) => !isset($shown[$item['id'] ?? null])));
    if (count($related) > 100) $related = array_slice($related, 0, 100);

    $best_matches = rank_best_matches($subject, $media_type, [
        'collection' => $collection_parts,
        'recommendations' => $recommended,
        'keywords' => array_slice($fetched['discover_keywords']['results'] ?? [], 0, 20),
        'similar' => $similar,
        'director' => array_slice($director_pool, 0, 12),
        'cast' => array_slice($cast_pool, 0, 40),
        'genre_era' => array_slice($fetched['discover_genre']['results'] ?? [], 0, 20),
    ], [
        'genre_ids' => $genre_ids,
        'year' => $subject_year,
        'language' => $language,
        'cast_share' => $cast_share,
        'director_ids' => $director_ids,
    ], 10);

    // Thematic shelf: gather each keyword bucket back out of the round.
    $keyword_names = concept_anchor_names();
    foreach ($keywords as $keyword) {
        if (!empty($keyword['id'])) $keyword_names[(int) $keyword['id']] = $keyword['name'] ?? '';
    }
    $buckets = [];
    foreach ($fetched as $key => $data) {
        if (!$data || strpos($key, 'kw:') !== 0) continue;
        [, $media, $keyword_id] = explode(':', $key);
        $buckets[] = [
            'media' => $media,
            'name' => $keyword_names[(int) $keyword_id] ?? '',
            'total' => $data['total_results'] ?? count($data['results'] ?? []),
            'items' => array_slice($data['results'] ?? [], 0, 20),
        ];
    }
    // Anything already ranked in the closest-10 shelf is skipped, so the two
    // shelves complement rather than repeat each other.
    $exclude = array_flip(array_column($best_matches, 'id'));
    $thematic = rank_thematic_matches($subject, $media_type, $buckets, $keywords, $exclude, 18);

    return [
        'id' => $id,
        'title' => $title,
        'name' => $title,
        'description' => $description,
        'imdb' => $imdb,
        'genres' => $genres,
        'genre_map' => $genre_map,
        'watch_url' => $watch_url,
        'director' => $director['name'] ?? '',
        'best_matches_length' => count($best_matches),
        'thematic_length' => count($thematic),
        'thematic' => $thematic,
        'similar_cast_genre_length' => count($similar_cast_genre),
        'similar_length' => count($similar),
        'related_length' => count($related),
        'best_matches' => $best_matches,
        'similar_cast_genre' => array_values($similar_cast_genre),
        'similar' => array_values($similar),
        'related' => array_values($related),
    ];
}

function find_movie_recommendations($movie, $locale) {
    return build_recommendations($movie, $locale, 'movie');
}

function find_show_recommendations($show, $locale) {
    return build_recommendations($show, $locale, 'tv');
}

function find_movie_recommendations_by_id($id, $locale) {
    $movie = tmdb_fetch("/movie/$id", 86400);
    if (!$movie) return null;
    return find_movie_recommendations($movie, $locale);
}

function find_show_recommendations_by_id($id, $locale) {
    $show = tmdb_fetch("/tv/$id", 86400);
    if (!$show) return null;
    return find_show_recommendations($show, $locale);
}

/**
 * Resolves everything a search results page needs. The subject lookup is one
 * round trip; build_recommendations() then does the rest in two parallel
 * batches. Popular and top-rated lists no longer load here — they say nothing
 * about the title being searched for, so they live on the home page instead.
 */
function get_recommendations_page($type, $id, $name, $locale = 'US') {
    if ($type !== 'movie' && $type !== 'tv') return null;

    $base = $type === 'tv' ? '/tv' : '/movie';

    if (!empty($id)) {
        $subject = tmdb_fetch("$base/$id", 86400);
    } else {
        $found = $type === 'tv' ? find_shows($name) : find_movies($name);
        $subject = pick_search_match($found, $name);
        // Search results are trimmed down; the detail record carries the
        // franchise, runtime and IMDb id the engine and hero want.
        if (!empty($subject['id'])) {
            $detail = tmdb_fetch("$base/{$subject['id']}", 86400);
            if ($detail) $subject = $detail;
        }
    }
    if (!$subject) return null;

    $recommendations = build_recommendations($subject, $locale, $type);
    if (!$recommendations) return null;

    $genre_map = $recommendations['genre_map'];
    $recommendations['subject'] = $subject;
    $recommendations['backdrop'] = tmdb_backdrop($subject);
    $recommendations['poster'] = tmdb_poster($subject);
    $recommendations['year'] = tmdb_year($subject);
    $recommendations['rating'] = tmdb_rating($subject);
    $recommendations['genre_names'] = tmdb_genre_names($subject, $genre_map, 4);

    return $recommendations;
}

/**
 * The home page's browse shelves: popular and top rated for both media types,
 * plus both genre maps, fetched as a single parallel batch.
 */
function get_home_lists() {
    $data = tmdb_fetch_multi([
        'popular_movies' => '/movie/popular',
        'top_rated_movies' => '/movie/top_rated',
        'popular_shows' => '/tv/popular',
        'top_rated_shows' => '/tv/top_rated',
        'movie_genres' => '/genre/movie/list',
        'tv_genres' => '/genre/tv/list',
    ], [
        'popular_movies' => 21600, 'top_rated_movies' => 21600,
        'popular_shows' => 21600, 'top_rated_shows' => 21600,
        'movie_genres' => 604800, 'tv_genres' => 604800,
    ]);

    $list = function ($key) use ($data) {
        $items = $data[$key]['results'] ?? [];
        $items = array_values(array_filter($items, 'tmdb_has_artwork'));
        return array_slice($items, 0, 18);
    };
    $map = function ($key) use ($data) {
        $out = [];
        foreach ($data[$key]['genres'] ?? [] as $genre) {
            if (isset($genre['id'], $genre['name'])) $out[$genre['id']] = $genre['name'];
        }
        return $out;
    };

    return [
        'popular_movies' => $list('popular_movies'),
        'top_rated_movies' => $list('top_rated_movies'),
        'popular_shows' => $list('popular_shows'),
        'top_rated_shows' => $list('top_rated_shows'),
        'movie_genres' => $map('movie_genres'),
        'tv_genres' => $map('tv_genres'),
    ];
}

// ---------------------------------------------------------------------------
// General lists
// ---------------------------------------------------------------------------

function find_popular_shows() {
    $data = tmdb_fetch("/tv/popular", 21600);
    return array_slice($data['results'] ?? [], 0, 20);
}

function find_top_rated_shows() {
    $data = tmdb_fetch("/tv/top_rated", 21600);
    return array_slice($data['results'] ?? [], 0, 20);
}

function find_latest_show() {
    return tmdb_fetch("/tv/latest", 3600) ?? [];
}

function find_popular_movies() {
    $data = tmdb_fetch("/movie/popular", 21600);
    return array_slice($data['results'] ?? [], 0, 20);
}

function find_top_rated_movies() {
    $data = tmdb_fetch("/movie/top_rated", 21600);
    return array_slice($data['results'] ?? [], 0, 20);
}

function find_latest_movie() {
    return tmdb_fetch("/movie/latest", 3600) ?? [];
}
