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
    <h1>CS 451 Final Project Resources Page</h1>

    <form action="index.php" method="get" class="home-button-form">
        <button type="submit" class="home-button">
            <img src="buttonImages/home-button.png" alt="Return Home" class="home-button-image">
        </button>
    </form>
    <h2>ER Diagram</h2>
    <img id="er-diagram" src="<?php echo "ERD.png"; ?>" alt="ER Diagram">

    <h2>Front End Code</h2>
    <a href="txtFiles/index.txt" >Contents</a> of the index PHP page. <br>
    <a href="txtFiles/items.txt" >Contents</a> of the items PHP page. <br>
    <a href="txtFiles/stores.txt" >Contents</a> of the stores PHP page. <br>
    <a href="txtFiles/styles.txt" >Contents</a> of the styles css page. <br>
    <a href="connectionData.txt" >Contents</a> of the connection data txt file. <br>
    <a href="txtFiles/resources.txt" >Contents</a> of the resources PHP page. <br>
    
    <h2>Back End Code</h2>
    <a href="txtFiles/app.txt" >Contents</a> of the Flask app Python file. <br>
    <a href="txtFiles/Dockerfile.txt" >Contents</a> of the Dockerfile. <br>
    <a href="txtFiles/models.txt" >Contents</a> of the sqlalchemy models Python file. <br>

    <h2>Tables</h2>
    <a href="txtFiles/tables.txt" >Contents</a> of the tables PHP page. <br>
    <a href="tables.php" >Tables</a> and their contents. <br>

    <h2>Queries Used</h2>
    <a href="txtFiles/CS451FinalProjectQueries.txt" >Translated</a> queries from ORM to basic SQL <br>
</body>
</html>