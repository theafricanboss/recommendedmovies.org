<?php
require './inc/functions.php';
require './inc/listing.php';

// Browse shelves live here rather than on the results page: they describe what
// is worth watching in general, not what relates to a particular search.
$home = get_home_lists();

// Real titles as one-tap starting points, from the cached popular list.
$suggestions = array_slice(array_filter(array_map('tmdb_title', $home['popular_movies'])), 0, 5);

$page_title = 'Recommended Movies — find what to watch next';
$page_description = 'Search any movie or TV show and get recommendations built from its cast, genres and closest matches.';
$show_header_search = true;
include './inc/header.php';
?>
<style>
    /* The hero carries its own search, so the sticky header is hidden here —
       which means the shelf nav becomes the thing that sticks to the top. */
    .site-header { display: none; }
    .subnav { top: 0; }
    html { scroll-padding-top: 88px; }
</style>

    <section class="shell hero-home">
        <div>
            <p class="hero-home__eyebrow">
                <b>&#9733;</b> cast, genre &amp; similarity matching
            </p>

            <h1 class="hero-home__title">Find your next <span>favourite</span> in seconds</h1>

            <p class="hero-home__sub">
                Type one that you loved. We'll pull together the titles that share its
                cast, its genres and its closest matches — thousands of listings, ranked.
            </p>

            <div class="hero-home__form">
                <form class="searchbar searchbar--lg" method="get" action="/search.php" role="search">
                    <label class="sr-only" for="hero-q">Movie or show title</label>
                    <input id="hero-q" class="searchbar__input" type="text" name="n" placeholder="Type a title &hellip;" autocomplete="off" autofocus required>
                    <div class="segmented" role="radiogroup" aria-label="Type">
                        <input id="hero-movie" type="radio" name="t" value="movie" checked>
                        <label for="hero-movie">Movies</label>
                        <input id="hero-tv" type="radio" name="t" value="tv">
                        <label for="hero-tv">TV Shows</label>
                    </div>
                    <button class="btn btn--accent" type="submit">
                        Search
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <?php if ($suggestions) : ?>
                <div class="hero-home__suggestions">
                    <span class="label">Trending now:</span>
                    <?php foreach ($suggestions as $suggestion) : ?>
                    <a class="chip" href="/search.php?t=movie&amp;n=<?php echo urlencode($suggestion); ?>"><?php echo htmlspecialchars($suggestion); ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <nav class="subnav" aria-label="Jump to a section">
        <div class="shell subnav__inner">
            <div class="subnav__links">
                <a class="subnav__link" href="#popular-movies">Popular movies <b><?php echo count($home['popular_movies']); ?></b></a>
                <a class="subnav__link" href="#top-rated-movies">Top rated movies <b><?php echo count($home['top_rated_movies']); ?></b></a>
                <a class="subnav__link" href="#popular-shows">Popular shows <b><?php echo count($home['popular_shows']); ?></b></a>
                <a class="subnav__link" href="#top-rated-shows">Top rated shows <b><?php echo count($home['top_rated_shows']); ?></b></a>
            </div>
            <div class="filter">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                <label class="sr-only" for="filter">Filter these listings</label>
                <input id="filter" type="search" placeholder="Filter listings&hellip;" data-filter autocomplete="off">
            </div>
        </div>
    </nav>

    <?php
    shelf('popular-movies', 'Popular movies', $home['popular_movies'], 'movie', $home['movie_genres'], 'Trending with audiences right now');
    shelf('top-rated-movies', 'Top rated movies', $home['top_rated_movies'], 'movie', $home['movie_genres'], 'The highest scored films of all time');
    shelf('popular-shows', 'Popular TV shows', $home['popular_shows'], 'tv', $home['tv_genres'], 'What everyone is streaming this week');
    shelf('top-rated-shows', 'Top rated TV shows', $home['top_rated_shows'], 'tv', $home['tv_genres'], 'The highest scored series of all time');
    ?>

<?php include './inc/footer.php'; ?>
