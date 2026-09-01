<?php
require './inc/functions.php';

$id = isset($_GET['id']) ? secure_input($_GET['id']) : null;
$name = isset($_GET['n']) ? secure_input($_GET['n']) : null;
$type = isset($_GET['t']) ? secure_input($_GET['t']) : null;

if (!(($type && $id) || ($type && $name))) {
    echo_error("Add a title to search for, and pick whether it's a movie or a TV show.", 400);
}

$results = get_recommendations_page($type, $id, $name, "US");

if (!is_array($results)) {
    echo_error("We couldn't find a" . ($type === 'tv' ? ' show' : ' movie') . " matching \"" . ($name ?: $id) . "\".", 404);
}

require './inc/listing.php';

$is_tv = $type === 'tv';
$noun = $is_tv ? 'Shows' : 'Movies';
$genre_map = $results['genre_map'];

$shelves = [
    ['id' => 'recommended', 'title' => "Recommended based on theme", 'plain' => 'Top Recommended', 'items' => $results['thematic'], 'hint' => 'Films and shows about the same thing, whichever medium they are in', 'collapse' => 0, 'feature' => true],
    ['id' => 'cast-genre', 'title' => "Same cast & genre", 'plain' => "Same cast & genre", 'items' => $results['similar_cast_genre'], 'hint' => 'Shares actors and at least one genre with this title', 'collapse' => 18, 'feature' => false],
    ['id' => 'closest', 'title' => "Closely Ranked", 'plain' => 'Closely ranked', 'items' => $results['best_matches'], 'hint' => 'Ranked across franchise, crew, cast, themes, genre, era and audience scores', 'collapse' => 0, 'feature' => true],
    ['id' => 'related', 'title' => "Other related from this cast", 'plain' => 'Other related', 'items' => $results['related'], 'hint' => 'Everything the leads have appeared in', 'collapse' => 24, 'feature' => false],
    // ['id' => 'similar', 'title' => "Similar $noun", 'plain' => "Similar $noun", 'items' => $results['similar'], 'hint' => "TMDB's closest matches", 'collapse' => 0, 'feature' => false],
];
$shelves = array_values(array_filter($shelves, fn($s) => !empty($s['items'])));
$total = array_sum(array_map(fn($s) => count($s['items']), $shelves));

$page_title = $results['name'] . ' — recommendations | Recommended Movies';
$page_description = 'Movies and TV shows like ' . $results['name'] . '. ' . mb_substr($results['description'], 0, 150);
$page_image = $results['backdrop'];
$header_query = $results['name'];
$header_type = $type;
include './inc/header.php';
?>

    <section class="hero">
        <?php if ($results['backdrop']) : ?>
        <div class="hero__backdrop" aria-hidden="true">
            <img src="<?php echo htmlspecialchars($results['backdrop']); ?>" alt="" width="1280" height="720" fetchpriority="high" decoding="async">
        </div>
        <?php endif; ?>

        <div class="shell hero__inner">
            <div class="hero__poster">
                <?php if ($results['poster']['src']) : ?>
                <img src="<?php echo htmlspecialchars($results['poster']['src']); ?>"
                     alt="<?php echo htmlspecialchars($results['name']); ?> poster"
                     width="342" height="513" fetchpriority="high" decoding="async">
                <?php endif; ?>
            </div>

            <div class="hero__head">
                <span class="hero__eyebrow">Recommendations based on</span>
                <h1 class="hero__title"><?php echo htmlspecialchars($results['name']); ?></h1>

                <div class="hero__meta">
                    <?php if ($results['rating']) : ?>
                    <span class="rating">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1L12 2Z"/></svg>
                        <?php echo htmlspecialchars($results['rating']); ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($results['year']) : ?><span class="chip"><?php echo htmlspecialchars($results['year']); ?></span><?php endif; ?>
                    <span class="chip"><?php echo $is_tv ? 'TV Show' : 'Movie'; ?></span>
                    <?php foreach ($results['genre_names'] as $genre) : ?>
                    <span class="chip"><?php echo htmlspecialchars($genre); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hero__body">
                <?php if ($results['description']) : ?>
                <p class="hero__overview"><?php echo htmlspecialchars($results['description']); ?></p>
                <?php endif; ?>

                <div class="hero__actions">
                    <?php if ($results['watch_url']) : ?>
                    <a class="btn btn--accent" href="<?php echo htmlspecialchars($results['watch_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        Where to watch
                    </a>
                    <?php endif; ?>
                    <?php if ($results['imdb'] !== '#' && $results['imdb'] !== '') : ?>
                    <a class="btn" href="<?php echo htmlspecialchars($results['imdb']); ?>" target="_blank" rel="noopener noreferrer">View on IMDb</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($shelves) : ?>
    <nav class="subnav" aria-label="Jump to a section">
        <div class="shell subnav__inner">
            <div class="subnav__links">
                <?php foreach ($shelves as $shelf) : ?>
                <a class="subnav__link" href="#<?php echo htmlspecialchars($shelf['id']); ?>">
                    <?php echo htmlspecialchars($shelf['plain']); ?> <b><?php echo count($shelf['items']); ?></b>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="filter">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                <label class="sr-only" for="filter">Filter these <?php echo $total; ?> listings</label>
                <input id="filter" type="search" placeholder="Filter <?php echo $total; ?> listings&hellip;" data-filter autocomplete="off">
            </div>
        </div>
    </nav>

    <?php foreach ($shelves as $shelf) {
        shelf($shelf['id'], $shelf['title'], $shelf['items'], $type, $genre_map, $shelf['hint'], $shelf['collapse'], $shelf['feature']);
    } ?>
    <?php else : ?>
    <section class="shell shelf">
        <p class="no-results">No recommendations came back for this title. Try another search.</p>
    </section>
    <?php endif; ?>

<?php include './inc/footer.php'; ?>
