<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title>Recommended Movies</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/style.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.12.0/css/all.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.9.0/css/all.css">
</head>

<body class="bg-dark p-5">
    <section class="container py-5 text-center d-flex flex-column justify-content-center align-items-center vh-100 header-home-add">
        <h5 id="search-home-title" class="text-center sht-heading">Find Recommended Movies, TV shows and more</h5>
        <div id="search-home" class="d-flex justify-content-center align-items-center justify-content-xxl-center search-content">
            <form class="d-flex flex-row justify-content-around align-items-center justify-content-xxl-center" style="max-width: 80%;width: 650px;" method="get" action="/search.php">
            <input class="form-control search-input" type="text" name="n" placeholder="Enter title ..." autocomplete="on" required />
                <div class="mx-3">
                    <div class="form-check"><input id="movie" class="form-check-input" type="radio" name="t" value="movie" required /><label class="form-check-label" for="movie">Movie</label></div>
                    <div class="form-check"><input id="tv" class="form-check-input" type="radio" name="t" value="tv" required /><label class="form-check-label" for="tv">TV Show</label></div>
                </div><button class="btn btn-primary btn-primary-submit ms-3" type="submit"><i class="fas fa-arrow-right"></i></button>
            </form>
        </div>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>