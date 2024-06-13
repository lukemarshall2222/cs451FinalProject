<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CS 451 Final Project</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <link href='https://fonts.googleapis.com/css?family=Merriweather' rel='stylesheet'
     type='text/css'>
</head>
<body>
    <h1>CS 451 Final Project Main Page</h1>
    <div class="button-holder">
        <form action="items.php" method="get" class="redirection-form">
            <button type="submit" class="redirect-button">
                <img src="items.png" alt="Go to Items Dashboard Page" class="button-image">
            </button>
        </form>
        <form action="stores.php" method="get" class="redirection-form">
            <button type="submit" class="redirect-button">
                <img src="stores.png" alt="Go to Stores Dashboard" class="button-image">
            </button>
        </form>
    </div>
</body>
</html>