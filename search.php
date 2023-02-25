<?php
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);

require './inc/functions.php';

$id = isset($_GET['id']) ? secure_input($_GET['id']) : null;
$name = isset($_GET['n']) ? secure_input($_GET['n']) : null;
$type = isset($_GET['t']) ? secure_input($_GET['t']) : null;

if ($type && $id) {
    // http://localhost:5555/search.php?t=movie&id=24
} else if ($type && $name) {
    // http://localhost:5555/search.php?t=movie&n=the%20matrix
} else {
  echo_error("Missing parameters.");
}

$recommendations = null;

if ($type == "movie") {
    if (empty($id)) {
    $movies = find_movies($name);
    // echo $movies[0] . "\n";
    if (is_array($movies) && count($movies) > 0) $recommendations = find_movie_recommendations($movies[0], "US");
    // echo $recommendations . "\n";
    // echo json_encode($recommendations);
    } else {
        // http://localhost:5555/search.php?t=movie&id=24
        $recommendations = find_movie_recommendations_by_id($id, "US");
    }
} else if ($type == "tv") {
    if (empty($id)) {
    $shows = find_shows($name);
    // echo $shows[0]['id'] . "\n";
    if (is_array($shows) && count($shows) > 0) $recommendations = find_show_recommendations($shows[0], "US");
    // echo $recommendations . "\n";
    // echo json_encode($recommendations);
    } else {
        // http://localhost:5555/search.php?t=tv&id=129430
        $recommendations = find_show_recommendations_by_id($id, "US");
    }
}

// if recommendations is not an object, redirect to home page
if (!is_array($recommendations)) {
    echo_error("No recommendations found.");
}

// $encodedname = urlencode($name);
// $results = file_get_contents("https://socialcane.com/movies?n=$encodedname&t=$type");

$results = $recommendations;
// print_r($results);

function get_results($item) {
    // print_r($item);
    $type = $_GET['t'];
    $id = isset($item['id']) ? $item['id'] : '';
    $name = isset($item["name"])
        ? $item["name"]
        : ( isset($item["original_name"])
        ? $item["original_name"]
        : ( isset($item["original_title"])
        ? $item["original_title"]
        : ""));
    $description = isset($item["description"])
        ? $item["description"]
        : ( isset($item["overview"])
        ? $item["overview"]
        : "");
    $image = isset($item['backdrop_path']) ? "https://image.tmdb.org/t/p/w500".$item['backdrop_path'] : './assets/default.jpg';
    // $url = isset($item['url']) ? $item['url'] : "/search.php?t=$type&id=$id"; // search by id is down
    $url = isset($item['url']) ? $item['url'] : "/search.php?t=$type&n=$name";
    $date = isset($item['first_air_date']) ? $item['first_air_date'] : ( isset($item["release_date"])
        ? $item["release_date"]
        : "");
    $duration = isset($item['duration']) ? $item['duration'] : '';
    if (empty($name)) return;
    $html = '<div class="col-12 col-sm-6 col-md-4 col-lg-2 flw-item">
                <div class="film-poster">
                <img title="' . $description . '" alt="' . $name . '" class="film-poster-img lazyload" src="' . $image . '">
                <div class="film-poster-overlay" data-toggle="tooltip" data-placement="top" title="' . $description . '">
                    <p class="d-none film-poster-ahref p-3">' . $description . '<br></p>
                    <a href="' . $url . '" title="' . $description . '" class="film-poster-ahref"><i class="fa fa-link"></i></a>
                </div>
                </div>
                <div class="film-detail film-detail-fix">
                    <h3 class="film-name"><a href="' . $url . '" title="' . $description . '">' . $name . '</a></h3>
                    <div class="fd-infor">
                    <span class="fdi-item">' . $date . '</span><span class="dot"></span>
                    <span class="fdi-item fdi-duration">' . $duration . '</span>
                    <span class="float-right fdi-type">' . strtoupper($type) . '</span>
                    </div>
                </div>
            </div>';
    echo $html;
    }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?php echo $results['name']; ?> | Recommended Movies</title>
    <meta name="description" content="Recommended movies, TV shows like <?php echo $results['name']; ?>. <?php echo $results['description']; ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.9.0/css/all.css">
</head>

<body class="bg-dark p-5">
    <section class="container py-5 header-home-add text-center">
        <h5 class="sht-heading" id="search-home-title">Find More Recommended Movies, TV shows <?php echo "like {$results['name']}"; ?></h5>
        <p class=""><?php echo $results['description']; ?></p>
        <?php if ($results['imdb'] != "" && $results['imdb'] != "#") : ?>
        <p class="">
            <a href="<?php echo $results['imdb']; ?>" target="_blank" rel="noopener noreferrer"><svg id="home_img" class="ipc-logo" xmlns="http://www.w3.org/2000/svg" width="64" height="32" viewBox="0 0 64 32" version="1.1"><g fill="#F5C518"><rect x="0" y="0" width="100%" height="100%" rx="4"></rect></g><g transform="translate(8.000000, 7.000000)" fill="#000000" fill-rule="nonzero"><polygon points="0 18 5 18 5 0 0 0"></polygon><path d="M15.6725178,0 L14.5534833,8.40846934 L13.8582008,3.83502426 C13.65661,2.37009263 13.4632474,1.09175121 13.278113,0 L7,0 L7,18 L11.2416347,18 L11.2580911,6.11380679 L13.0436094,18 L16.0633571,18 L17.7583653,5.8517865 L17.7707076,18 L22,18 L22,0 L15.6725178,0 Z"></path><path d="M24,18 L24,0 L31.8045586,0 C33.5693522,0 35,1.41994415 35,3.17660424 L35,14.8233958 C35,16.5777858 33.5716617,18 31.8045586,18 L24,18 Z M29.8322479,3.2395236 C29.6339219,3.13233348 29.2545158,3.08072342 28.7026524,3.08072342 L28.7026524,14.8914865 C29.4312846,14.8914865 29.8796736,14.7604764 30.0478195,14.4865461 C30.2159654,14.2165858 30.3021941,13.486105 30.3021941,12.2871637 L30.3021941,5.3078959 C30.3021941,4.49404499 30.272014,3.97397442 30.2159654,3.74371416 C30.1599168,3.5134539 30.0348852,3.34671372 29.8322479,3.2395236 Z"></path><path d="M44.4299079,4.50685823 L44.749518,4.50685823 C46.5447098,4.50685823 48,5.91267586 48,7.64486762 L48,14.8619906 C48,16.5950653 46.5451816,18 44.749518,18 L44.4299079,18 C43.3314617,18 42.3602746,17.4736618 41.7718697,16.6682739 L41.4838962,17.7687785 L37,17.7687785 L37,0 L41.7843263,0 L41.7843263,5.78053556 C42.4024982,5.01015739 43.3551514,4.50685823 44.4299079,4.50685823 Z M43.4055679,13.2842155 L43.4055679,9.01907814 C43.4055679,8.31433946 43.3603268,7.85185468 43.2660746,7.63896485 C43.1718224,7.42607505 42.7955881,7.2893916 42.5316822,7.2893916 C42.267776,7.2893916 41.8607934,7.40047379 41.7816216,7.58767002 L41.7816216,9.01907814 L41.7816216,13.4207851 L41.7816216,14.8074788 C41.8721037,15.0130276 42.2602358,15.1274059 42.5316822,15.1274059 C42.8031285,15.1274059 43.1982131,15.0166981 43.281155,14.8074788 C43.3640968,14.5982595 43.4055679,14.0880581 43.4055679,13.2842155 Z"></path></g></svg></a>
        </p>
        <?php endif; ?>
        <div id="search-home" class="d-flex justify-content-center align-items-center justify-content-xxl-center search-content">
            <form class="d-flex flex-row justify-content-around align-items-center justify-content-xxl-center" style="max-width: 80%;width: 650px;" method="get" action="/search.php">
            <input class="form-control search-input" type="text" name="n" placeholder="<?php echo $results['name']; ?>" autocomplete="on" required />
                <div class="mx-3">
                    <div class="form-check"><input id="movie" class="form-check-input" type="radio" name="t" value="movie" required /><label class="form-check-label" for="movie">Movie</label></div>
                    <div class="form-check"><input id="tv" class="form-check-input" type="radio" name="t" value="tv" required /><label class="form-check-label" for="tv">TV Show</label></div>
                </div><button class="btn btn-primary btn-primary-submit ms-3" type="submit"><i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </section>
    <?php if ($results['similar_cast_genre_shows_length'] > 0 || $results['similar_cast_genre_movies_length'] > 0) { ?>
    <section class="container">
        <div class="film_list film_list-grid">
            <h5 class="sht-heading"><?php if ($type == "tv") {
                echo $results['similar_cast_genre_shows_length'] . " Similar Cast & Genre Shows";
            } else {
                echo $results['similar_cast_genre_movies_length'] . " Similar Cast & Genre Movies";
            } ?></h5>
            <div class="row film_list-wrap">
                <?php
                if (isset($results['similar_cast_genre_shows'])) {
                    foreach ($results['similar_cast_genre_shows'] as $item) {
                        get_results($item);
                    }
                } else if (isset($results['similar_cast_genre_movies'])) {
                    foreach ($results['similar_cast_genre_movies'] as $item) {
                        get_results($item);
                    }
                }
                ?>
            </div>
        </div>
    </section>
    <?php } ?>
    <?php if ($results['similar_shows_length'] > 0 || $results['similar_movies_length'] > 0) { ?>
    <section class="container">
        <div class="film_list film_list-grid">
            <h5 class="sht-heading"><?php if ($type == "tv") {
                echo $results['similar_shows_length'] . " Similar Shows";
            } else {
                echo $results['similar_movies_length'] . " Similar Movies";
            } ?></h5>
            <div class="row film_list-wrap">
                <?php
                if (isset($results['similar_shows'])) {
                    foreach ($results['similar_shows'] as $item) {
                        get_results($item);
                    }
                } else if (isset($results['similar_movies'])) {
                    foreach ($results['similar_movies'] as $item) {
                        get_results($item);
                    }
                }
                ?>
            </div>
        </div>
    </section>
    <?php } ?>

    <section class="container">
        <div class="film_list film_list-grid">
            <h5 class="sht-heading"><?php if ($type == "tv") {
                $popular_shows = find_popular_shows();
                echo count($popular_shows) . " Popular Shows";
            } else {
                $popular_movies = find_popular_movies();
                echo count($popular_movies) . " Popular Movies";
            } ?></h5>
            <div class="row film_list-wrap">
                <?php
                if (isset($popular_shows) &&  count($popular_shows) > 0) {
                    foreach ($popular_shows as $item) {
                        get_results($item);
                    }
                } else if (isset($popular_movies) && count($popular_movies) > 0) {
                    foreach ($popular_movies as $item) {
                        get_results($item);
                    }
                }
                ?>
            </div>
        </div>
    </section>

    <section class="container">
        <div class="film_list film_list-grid">
            <h5 class="sht-heading"><?php if ($type == "tv") {
                $top_rated_shows = find_top_rated_shows();
                echo count($top_rated_shows) . " Recommended Shows";
            } else {
                $top_rated_movies = find_top_rated_movies();
                echo count($top_rated_movies) . " Recommended Movies";
            } ?></h5>
            <div class="row film_list-wrap">
                <?php
                if (isset($top_rated_shows) &&  count($top_rated_shows) > 0) {
                    foreach ($top_rated_shows as $item) {
                        get_results($item);
                    }
                } else if (isset($top_rated_movies) && count($top_rated_movies) > 0) {
                    foreach ($top_rated_movies as $item) {
                        get_results($item);
                    }
                }
                ?>
            </div>
        </div>
    </section>

    <section class="container d-none">
        <div class="film_list film_list-grid">
            <h5 class="sht-heading"><?php if ($type == "tv") {
                echo "Latest Show";
                // $item = find_latest_show();
            } else {
                echo "Latest Movie";
                // $item = find_latest_movie();
            } ?></h5>
            <div class="row film_list-wrap">
                <?php if (isset($item)) { get_results($item); } ?>
            </div>
        </div>
    </section>

    <?php if ($results['shows_length'] > 0 || $results['movies_length'] > 0) { ?>
    <section class="container">
        <div class="film_list film_list-grid">
            <h5 class="sht-heading"><?php if ($type == "tv") {
                echo $results['shows_length'] . " Related Shows";
            } else {
                echo $results['movies_length'] . " Related Movies";
            } ?></h5>
            <div class="row film_list-wrap">
                <?php
                if (isset($results['shows'])) {
                    get_results(find_latest_show());
                    foreach ($results['shows'] as $item) {
                        get_results($item);
                    }
                } else if (isset($results['movies'])) {
                    get_results(find_latest_movie());
                    foreach ($results['movies'] as $item) {
                        get_results($item);
                    }
                }
                ?>
            </div>
        </div>
    </section>
    <?php } ?>

    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $(function () {
                $('[data-toggle="tooltip"]').tooltip()
            })
            // $('.film_list-wrap').slick({
            //     infinite: true,
            //     slidesToShow: 6,
            //     slidesToScroll: 6,
            //     responsive: [{
            //             breakpoint: 1200,
            //             settings: {
            //                 slidesToShow: 5,
            //                 slidesToScroll: 5,
            //             }
            //         },
            //         {
            //             breakpoint: 992,
            //             settings: {
            //                 slidesToShow: 4,
            //                 slidesToScroll: 4,
            //             }
            //         },
            //         {
            //             breakpoint: 768,
            //             settings: {
            //                 slidesToShow: 3,
            //                 slidesToScroll: 3,
            //             }
            //         },
            //         {
            //             breakpoint: 576,
            //             settings: {
            //                 slidesToShow: 2,
            //                 slidesToScroll: 2,
            //             }
            //         }
            //     ]
            // });
        });
    </script>
</body>

</html>