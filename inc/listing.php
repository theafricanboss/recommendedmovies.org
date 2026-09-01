<?php
/**
 * Shared listing renderers used by both the home page and the results page.
 * Everything a card needs is normalised in functions.php, so these only lay
 * it out and escape it.
 */

function card($item, $type, $genre_map = []) {
    $name = tmdb_title($item);
    if ($name === '') return;

    // The thematic shelf mixes films and shows, so an item may override the
    // shelf's type — that keeps its link and its badge correct.
    $type = $item['media_type'] ?? $type;

    $art = tmdb_poster($item);
    $overview = tmdb_overview($item);
    $rating = tmdb_rating($item);
    $year = tmdb_year($item);
    $genres = tmdb_genre_names($item, $genre_map, 1);
    $match = $item['match_score'] ?? null;
    $reasons = $item['match_reasons'] ?? [];
    $url = '/search.php?t=' . urlencode($type) . '&n=' . urlencode($name);
    ?>
    <article class="card" data-title="<?php echo htmlspecialchars(strtolower($name)); ?>">
        <a class="card__link" href="<?php echo htmlspecialchars($url); ?>">
            <div class="card__art">
                <?php if ($art['src']) : ?>
                <img src="<?php echo htmlspecialchars($art['src']); ?>"
                     <?php if ($art['srcset']) : ?>srcset="<?php echo htmlspecialchars($art['srcset']); ?>" sizes="<?php echo htmlspecialchars($art['sizes']); ?>"<?php endif; ?>
                     alt="<?php echo htmlspecialchars($name); ?> poster"
                     width="342" height="513" loading="lazy" decoding="async">
                <?php endif; ?>
                <div class="card__badges">
                    <?php if ($match !== null) : ?>
                    <span class="badge badge--match" title="Closeness to your search"><?php echo (int) $match; ?>% match</span>
                    <?php elseif ($rating) : ?>
                    <span class="badge">
                        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1L12 2Z"/></svg>
                        <?php echo htmlspecialchars($rating); ?>
                    </span>
                    <?php endif; ?>
                    <span class="badge badge--type"><?php echo $type === 'tv' ? 'TV' : 'FILM'; ?></span>
                </div>
                <?php if ($overview) : ?>
                <div class="card__veil">
                    <p class="card__synopsis"><?php echo htmlspecialchars($overview); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <h3 class="card__title"><?php echo htmlspecialchars($name); ?></h3>
            <div class="card__meta">
                <?php if ($match !== null && $rating) : ?>
                <span class="card__score">&#9733; <?php echo htmlspecialchars($rating); ?></span>
                <span class="dot"></span>
                <?php endif; ?>
                <?php if ($year) : ?><span><?php echo htmlspecialchars($year); ?></span><?php endif; ?>
                <?php if ($year && $genres) : ?><span class="dot"></span><?php endif; ?>
                <?php if ($genres) : ?><span><?php echo htmlspecialchars($genres[0]); ?></span><?php endif; ?>
            </div>
            <?php if ($reasons) : ?>
            <p class="card__reason"><?php echo implode(' &middot; ', array_map('htmlspecialchars', $reasons)); ?></p>
            <?php endif; ?>
        </a>
    </article>
    <?php
}

/** A titled grid of listings, collapsed past $collapse items. */
function shelf($id, $title, $items, $type, $genre_map = [], $hint = '', $collapse = 0, $feature = false) {
    if (!$items) return;
    $collapsible = $collapse > 0 && count($items) > $collapse;
    ?>
    <section class="shelf shell<?php echo $feature ? ' shelf--feature' : ''; ?>" id="<?php echo htmlspecialchars($id); ?>"<?php if ($collapsible) : ?> data-collapse="<?php echo (int) $collapse; ?>"<?php endif; ?>>
        <header class="shelf__head">
            <h2 class="shelf__title"><?php echo htmlspecialchars($title); ?></h2>
            <span class="count"><?php echo count($items); ?></span>
            <?php if ($hint) : ?><span class="shelf__hint"><?php echo htmlspecialchars($hint); ?></span><?php endif; ?>
        </header>
        <div class="grid">
            <?php foreach ($items as $item) card($item, $type, $genre_map); ?>
        </div>
        <?php if ($collapsible) : ?>
        <div class="shelf__more">
            <button class="btn" type="button" data-more aria-expanded="false">Show all <?php echo count($items); ?></button>
        </div>
        <?php endif; ?>
    </section>
    <?php
}
