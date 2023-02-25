<?php
require_once '././vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::create(dirname(__DIR__, 1), '.env');
$dotenv->load();
define('TMDB_API_KEY', getenv('TMDB_API_KEY'));
define('TMDB_ACCESS_TOKEN', getenv('TMDB_ACCESS_TOKEN'));
define('TMDB_URL', "https://api.themoviedb.org/3");

function secure_input($text) {
    $text = trim($text);
    $text = stripslashes($text);
    $text = htmlspecialchars($text);
    return $text;
}

function find_all_by_person($id) {
	$all = array();
	try {
		if ($id == null) return $all;
		$find_all_url = TMDB_URL . "/person/$id/combined_credits?api_key=" . TMDB_API_KEY;
        $request_body = file_get_contents($find_all_url);
        $find_all_Result = json_decode($request_body, true);
		if ($find_all_Result) {
			$cast_in = $find_all_Result['cast'];
			if (count($cast_in) > 5) {
				$cast_in = array_slice($cast_in, 0, 5);
			}
			$crew_in = $find_all_Result['crew'];
			if (count($crew_in) > 5) {
				$crew_in = array_slice($crew_in, 0, 5);
			}
			$find_all_results = array_merge($cast_in, $crew_in);
			for ($i = 0; $i < count($find_all_results); $i++) {
                array_push($all, $find_all_results[$i]);
			}
		}
		return $all;
	} catch (Exception $err) {
		echo $err->getMessage();
        return array();
		// return $err->getMessage();
	}
}

function find_movies($movie_title) {
	$movies = array();
	try {
		$find_movies_url = TMDB_URL . "/search/movie?api_key=" . TMDB_API_KEY . "&query=" . urlencode($movie_title);
		$find_movies_Result = json_decode(file_get_contents($find_movies_url));
        if ($find_movies_Result && $find_movies_Result->results) {
            $find_movies_results = $find_movies_Result->results;
            for ($i = 0; $i < count($find_movies_results); $i++) {
                $movies[] = $find_movies_results[$i];
            }
        }
		return $movies;
	} catch (Exception $err) {
		echo $err->getMessage();
        return array();
		// return $err->getMessage();
	}
}

function find_movie_url($id, $locale) {
	try {
		$url = "";
		if ($id == null) return $url;

		$find_movie_url = TMDB_URL . "/movie/$id/watch/providers?api_key=" . TMDB_API_KEY;
		$find_movie_Result = json_decode(file_get_contents($find_movie_url));

		if ($find_movie_Result && $find_movie_Result->results) {
			$find_movie_results = $find_movie_Result->results;
			if (isset($find_movie_results->$locale)) {
				$url = $find_movie_results->$locale->link;
			}
		}
		return $url;
	} catch (Exception $err) {
		echo $err->getMessage();
        return '';
		// return $err->getMessage();
	}
}

function find_movie_recommendations_by_id($id, $locale) {
    try {
		$find_movie_by_id_url = TMDB_URL . "/movie/$id?api_key=" . TMDB_API_KEY;
		$movie = json_decode(file_get_contents($find_movie_by_id_url), true);
    } catch (\Throwable $th) {
        //throw $th;
        return array();
    }
	return find_movie_recommendations($movie, $locale);
}

function find_movie_recommendations($movie, $locale) {
    // print_r($movie);
    $movies = array();
    $similar_cast_genre_movies = array();
    if (is_array($movie)) {
        $id = $movie['id'];
        $title = isset($movie['title']) ? $movie['title'] : (isset($movie['original_title']) ? $movie['original_title'] : array());
        $description = isset($movie["description"])
            ? $movie["description"]
            : ( isset($movie["overview"])
            ? $movie["overview"]
            : "");
        $imdb = isset($movie["imdb_id"])
        ? "https://imdb.com/title/{$movie["imdb_id"]}"
        : "#";
        $genres = isset($movie['genre_ids']) ? $show['genre_ids'] : (isset($movie['genres']) ? $movie['genres'] : array());
    } else if (is_object($movie)) {
        $id = $movie->id;
        $title = $movie->title;
        $description = isset($movie->description)
            ? $movie->description
            : ( isset($movie->overview)
            ? $movie->overview
            : "");
        $imdb = isset($movie->imdb_id) ? "https://imdb.com/title/{$movie->imdb_id}" : "#";
        $genres = isset($movie->genre_ids) ? $movie->genre_ids : (isset($movie->genres) ? $movie->genres : array());
    } else {
        return array();
    }
	try {
        $similar_movies_url = TMDB_URL . "/movie/$id/similar?api_key=" . TMDB_API_KEY;
		$similar_movies_Result = json_decode(file_get_contents($similar_movies_url), true);
        $similar_movies = $similar_movies_Result['results'];
        if (count($similar_movies) > 20) {
            $similar_movies = array_slice($similar_movies, 0, 20);
        }
		$find_moviecast_url = TMDB_URL . "/movie/$id/credits?api_key=" . TMDB_API_KEY;
		$find_moviecast_Result = json_decode(file_get_contents($find_moviecast_url), true);
		$find_movie_cast = $find_moviecast_Result['cast'];
		if (count($find_movie_cast) > 5) {
			$find_movie_cast = array_slice($find_movie_cast, 0, 5);
		}

        foreach ($find_movie_cast as $cast) {
            $find_movies = find_all_by_person($cast['id']);
            if ($find_movies && count($find_movies) > 0) {
				foreach ($find_movies as $find_movie) {
					$movies[] = $find_movie;
					for ($i = 0; $i < count($genres); $i++) {
						if (isset($find_movie->genre_ids[0]) && $find_movie->genre_ids[0] == $genres[$i]) {
							// $find_movie_url = find_movie_url($find_movie->id, $locale);
							$similar_cast_genre_movies[] = $find_movie;
						}
					}
				}
				if (count($movies) > 100) {
					$movies = array_slice($movies, 0, 100);
				}
			}
		}
		return array(
			'id' => $id,
			'title' => $title,
			'name' => $title,
            'description' => $description,
            'imdb' => $imdb,
			'genres' => $genres,
			'similar_cast_genre_movies_length' => count($similar_cast_genre_movies),
			'similar_movies_length' => count($similar_movies),
			'movies_length' => count($movies),
			'similar_cast_genre_movies' => array_values($similar_cast_genre_movies),
			'similar_movies' => array_values($similar_movies),
			'movies' => array_values($movies),
		);
	} catch (Exception $e) {
		echo $e->getMessage();
	}
}

function find_shows($show_name) {
  $shows = array();
  try {
    $find_shows_url = TMDB_URL . "/search/tv?api_key=" . TMDB_API_KEY . "&query=" . urlencode($show_name);
    $find_shows_Result = json_decode(file_get_contents($find_shows_url), true);
    if ($find_shows_Result && $find_shows_Result['results']) {
        $find_shows_results = $find_shows_Result['results'];
        for ($i = 0; $i < count($find_shows_results); $i++) {
          $shows[] = $find_shows_results[$i];
        }
    }
    return $shows;
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}

function find_show_url($id, $locale) {
  try {
    $url = "";
    if ($id == null) return $url;

    $find_show_url = TMDB_URL . "/tv/$id/watch/providers?api_key=" . TMDB_API_KEY;
    $find_show_Result = json_decode(file_get_contents($find_show_url), true);

    if (
      $find_show_Result &&
      $find_show_Result->results
    ) {
      $find_show_results = $find_show_Result->results;
      if (isset($find_show_results[$locale])) {
        $url = $find_show_results[$locale]['link'];
      }
    }
    return $url;
  } catch (Exception $err) {
      // echo $err->getMessage();
    // error_log($err->getMessage());
    return '';
  }
}

function find_show_recommendations_by_id($id, $locale) {
    try {
		$find_show_by_id_url = TMDB_URL . "/tv/$id?api_key=" . TMDB_API_KEY;
		$show = json_decode(file_get_contents($find_show_by_id_url), true);
    } catch (\Throwable $th) {
        //throw $th;
        return array();
    }
	return find_show_recommendations($show, $locale);
}

function find_show_recommendations($show, $locale) {
    // print_r($show);
    $shows = array();
    $similar_cast_genre_shows = array();
    if (is_array($show)) {
        $id = $show['id'];
        $name = isset($show['name']) ? $show['name'] : (isset($show['original_name']) ? $show['original_name'] : array());
        $description = isset($show["description"])
            ? $show["description"]
            : ( isset($show["overview"])
            ? $show["overview"]
            : "");
        $imdb = isset($show["imdb_id"])
        ? "https://imdb.com/title/{$show["imdb_id"]}"
        : "#";
        $genres = isset($show['genre_ids']) ? $show['genre_ids'] : (isset($show['genres']) ? $show['genres'] : array());
    } else if (is_object($show)) {
        $id = $show->id;
        $name = $show->name;
        $description = isset($show->description)
            ? $show->description
            : ( isset($show->overview)
            ? $show->overview
            : "");
        $imdb = isset($show->imdb_id) ? "https://imdb.com/title/{$show->imdb_id}" : "#";
        $genres = isset($show->genre_ids) ? $show->genre_ids : (isset($show->genres) ? $show->genres : array());
    } else {
        return array();
    }

  try {
    $similar_shows_url = TMDB_URL . "/tv/$id/similar?api_key=" . TMDB_API_KEY;
    $similar_shows_Result = json_decode(file_get_contents($similar_shows_url), true);
    $similar_shows = $similar_shows_Result['results'];
    if (count($similar_shows) > 20) {
      $similar_shows = array_slice($similar_shows, 0, 20);
    }

    $find_showcast_url = TMDB_URL . "/tv/$id/credits?api_key=" . TMDB_API_KEY;
    $find_showcast_Result = json_decode(file_get_contents($find_showcast_url), true);
    $find_show_cast = $find_showcast_Result['cast'];
    if (count($find_show_cast) > 5) {
      $find_show_cast = array_slice($find_show_cast, 0, 5);
    }

    foreach ($find_show_cast as $cast) {
      $find_shows = find_all_by_person($cast['id']);
      if ($find_shows && count($find_shows) > 0) {
        foreach ($find_shows as $find_show) {
          $shows[] = $find_show;
          for ($k = 0; $k < count($genres); $k++) {
            if ($find_show['genre_ids'] && count($find_show['genre_ids']) > 0 && $find_show['genre_ids'][0] == $genres[$k]) {
            //   $find_show_url = find_show_url($find_show['id'], $locale);
              $similar_cast_genre_shows[] = $find_show;
            }
          }
        }
        if (count($shows) > 100) {
          $shows = array_slice($shows, 0, 100);
        }
      }
    }

    return [
      'id' => $id,
      'title' => $name,
      'name' => $name,
      'description' => $description,
      'imdb' => $imdb,
      'genres' => $genres,
      'similar_cast_genre_shows_length' => count($similar_cast_genre_shows),
      'similar_shows_length' => count($similar_shows),
      'shows_length' => count($shows),
      'similar_cast_genre_shows' => array_values($similar_cast_genre_shows),
      'similar_shows' => array_values($similar_shows),
      'shows' => array_values($shows),
    ];
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}

// ------------------------------ GENERAL

// https://api.themoviedb.org/3/tv/popular?
function find_popular_shows() {
  $shows = array();
  try {
  $popular_shows_url = TMDB_URL . "/tv/popular?api_key=" . TMDB_API_KEY;
  $popular_shows_Result = json_decode(file_get_contents($popular_shows_url), true);
  $popular_shows = $popular_shows_Result['results'];
  if (count($popular_shows) > 20) {
    $popular_shows = array_slice($popular_shows, 0, 20);
  }
  foreach ($popular_shows as $popular_show) {
    $shows[] = $popular_show;
  }
  return $shows;
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}

// https://api.themoviedb.org/3/tv/top_rated?
function find_top_rated_shows() {
  $shows = array();
  try {
  $top_rated_shows_url = TMDB_URL . "/tv/top_rated?api_key=" . TMDB_API_KEY;
  $top_rated_shows_Result = json_decode(file_get_contents($top_rated_shows_url), true);
  $top_rated_shows = $top_rated_shows_Result['results'];
  if (count($top_rated_shows) > 20) {
    $top_rated_shows = array_slice($top_rated_shows, 0, 20);
  }
  foreach ($top_rated_shows as $top_rated_show) {
    $shows[] = $top_rated_show;
  }
  return $shows;
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}

// https://api.themoviedb.org/3/tv/latest?
function find_latest_show() {
  $show = array();
  try {
  $latest_show_url = TMDB_URL . "/tv/latest?api_key=" . TMDB_API_KEY;
  $show = json_decode(file_get_contents($latest_show_url), true);
  return $show;
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}


// https://api.themoviedb.org/3/movie/popular?
function find_popular_movies() {
  $movies = array();
  try {
  $popular_movies_url = TMDB_URL . "/movie/popular?api_key=" . TMDB_API_KEY;
  $popular_movies_Result = json_decode(file_get_contents($popular_movies_url), true);
  $popular_movies = $popular_movies_Result['results'];
  if (count($popular_movies) > 20) {
    $popular_movies = array_slice($popular_movies, 0, 20);
  }
  foreach ($popular_movies as $popular_movie) {
    $movies[] = $popular_movie;
  }
  return $movies;
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}

// https://api.themoviedb.org/3/movie/top_rated?
function find_top_rated_movies() {
  $movies = array();
  try {
  $top_rated_movies_url = TMDB_URL . "/movie/top_rated?api_key=" . TMDB_API_KEY;
  $top_rated_movies_Result = json_decode(file_get_contents($top_rated_movies_url), true);
  $top_rated_movies = $top_rated_movies_Result['results'];
  if (count($top_rated_movies) > 20) {
    $top_rated_movies = array_slice($top_rated_movies, 0, 20);
  }
  foreach ($top_rated_movies as $top_rated_movie) {
    $movies[] = $top_rated_movie;
  }
  return $movies;
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}

// https://api.themoviedb.org/3/movie/latest?
function find_latest_movie() {
  $movie = array();
  try {
  $latest_movie_url = TMDB_URL . "/movie/latest?api_key=" . TMDB_API_KEY;
  $movie = json_decode(file_get_contents($latest_movie_url), true);
  return $movie;
  } catch (Exception $err) {
    error_log($err->getMessage());
    return array();
    // return $err->getMessage();
  }
}