<?php
/**
 * Shared page chrome. Set any of these before including:
 *   $page_title, $page_description, $page_image
 *   $header_query, $header_type   — prefill the compact header search
 *   $show_header_search           — false on the home page (the hero has one)
 */
$page_title = $page_title ?? 'Recommended Movies — find what to watch next';
$page_description = $page_description ?? 'Search any movie or TV show and get recommendations built from its cast, genres and closest matches.';
$page_image = $page_image ?? '';
$header_query = $header_query ?? '';
$header_type = ($header_type ?? 'movie') === 'tv' ? 'tv' : 'movie';
$show_header_search = $show_header_search ?? true;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08090d">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>">
    <meta property="og:type" content="website">
    <?php if ($page_image) : ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($page_image); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://image.tmdb.org">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap">
    <link rel="stylesheet" href="/assets/app.css">
</head>

<body>
    <a class="sr-only" href="#main">Skip to content</a>

    <header class="site-header" data-site-header>
        <div class="shell site-header__inner">
            <a class="brand" href="/" aria-label="Recommended Movies home">Recommended Movies</a>

            <?php if ($show_header_search) : ?>
            <form class="searchbar" method="get" action="/search.php" role="search">
                <label class="sr-only" for="hdr-q">Search a title</label>
                <input id="hdr-q" class="searchbar__input" type="text" name="n" placeholder="Search a movie or show&hellip;" value="<?php echo htmlspecialchars($header_query); ?>" autocomplete="off" required>
                <div class="segmented" role="radiogroup" aria-label="Type">
                    <input id="hdr-movie" type="radio" name="t" value="movie" <?php echo $header_type === 'movie' ? 'checked' : ''; ?>>
                    <label for="hdr-movie">Movies</label>
                    <input id="hdr-tv" type="radio" name="t" value="tv" <?php echo $header_type === 'tv' ? 'checked' : ''; ?>>
                    <label for="hdr-tv">TV Shows</label>
                </div>
                <button class="btn btn--accent btn--icon" type="submit" aria-label="Search">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>
                </button>
            </form>
            <?php endif; ?>

            <div class="site-header__actions">
                <a class="btn btn--icon" href="https://github.com/theafricanboss/recommendedmovies.org" target="_blank" rel="noopener noreferrer" aria-label="View this project on GitHub">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.45-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.9 1.53 2.34 1.09 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.95 0-1.09.39-1.99 1.03-2.69-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.03a9.5 9.5 0 0 1 5 0c1.91-1.3 2.75-1.03 2.75-1.03.55 1.38.2 2.4.1 2.65.64.7 1.03 1.6 1.03 2.69 0 3.85-2.34 4.7-4.57 4.95.36.31.68.92.68 1.85v2.75c0 .26.18.58.69.48A10 10 0 0 0 12 2Z"/></svg>
                </a>
            </div>
        </div>
    </header>

    <main id="main">
