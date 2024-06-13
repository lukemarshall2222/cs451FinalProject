<!--Author: Luke Marshall
    CS 451 Final Project 
    php page for stores dashboard
    last modified: 
    contributors:
    Sources:
        mysqli: https://www.php.net/manual/en/book.mysqli.php
        dropdowns: https://stackoverflow.com/questions/40154810/dynamic-dropdown-list-with-php-mysql?newreg=55a1e958a85f4fb089a16820d73b05f6
    -->

<?php
error_reporting(E_ALL);
ini_set("display_errors",1);

// Initial queries for populating the dropdowns when HTML loads
include 'connectionData.txt'; // Connection data held in separate file

// Connection made through mysqli object, check it is successful
$conn = new mysqli($server, $user, $pass, $dbname, $port);
if ($conn->connect_error) {
    die("Connection was unsuccessful: " . $conn->connect_error);
}

// Function to get dropdown data
function dropdownQuery($conn, $query) {
    /* Args:
        $conn: mysqli connection object
        $query string: the query that should be run through the connection
       Returns:
        $options: array or associative array of the results of the query */
    $res = $conn->query($query);
    $options = [];
    if ($res && $res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $options[] = $row;
        }
    }
    return $options;
}

// Queries
$storeQuery = "SELECT s.store_id, s.name FROM store s";
$yearQuery = "SELECT DISTINCT YEAR(o.date) AS year FROM orders o";

// Get data for dropdowns
$stores = dropdownQuery($conn, $storeQuery);
$years = dropdownQuery($conn, $yearQuery);

// Close the connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Store Dashboard</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
    <link href='https://fonts.googleapis.com/css?family=Merriweather' rel='stylesheet'
     type='text/css'>
</head>
<body>
    <h1>Store Dashboard</h1>
    <form action="index.php" method="get" class="home-button-form"> 
        <button type="submit" class="home-button">
            <img src="buttonImages/home-button.png" alt="Return Home" class="home-button-image">
        </button>
    </form>

    <!-- Dropdowns -->
    <form method="post" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
        <!-- Store Dropdown -->
        <label for="stores">Choose a store:</label>
        <select id="stores" name="stores">
            <?php foreach ($stores as $store) { ?>
                <option value="<?php echo $store['store_id']; ?>"><?php echo $store['name']; ?></option>
            <?php } ?>
        </select><br>

        <!-- Years Dropdown -->
        <label for="years">Choose a Year:</label>
        <select id="years" name="years">
            <?php foreach ($years as $year) { ?>
                <option value="<?php echo $year['year']; ?>"><?php echo $year['year']; ?></option>
            <?php } ?>
        </select><br>

        <!-- Submit Button -->
        <button type="submit">Submit</button>
    </form>

    <?php
    // creating multiple cURL handles with same bit of code:
    function curlHandleFactory($url) {
        $handle = curl_init();
        // curl_init: "Initializes a new session and return a cURL handle for use with the curl_setopt(), curl_exec(), and 
        // curl_close() functions"
        curl_setopt($handle, CURLOPT_URL, $url); 
        // CURLOPT_URL: "The URL to fetch. This can also be set when initializing a session with curl_init()".
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        // CURLOPT_RETURNTRANSFER: "true to return the transfer as a string of the return value of  curl_exec() instead of 
        // outputting it directly."
        return $handle;
    }
    // accept selected elements from each dropdown when button pressed
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $selectedStore = $_POST["stores"];
        $selectedYear = $_POST["years"];

        foreach($stores as $store) { // find the selected items name
            if ($store['store_id'] == $selectedStore) {
                $storeName = $store['name'];
                break;
            }
        }
        echo "<h1>Graphs for " . htmlspecialchars($storeName) . " in the Months of " . $selectedYear. "</h1>";

        // for debugging dropdown selections:
        // echo "Selected Store ID: " . $selectedStore . "<br>";
        // echo "Selected Year: " . $selectedYear . "<br>";

        //to denote which endpoint should be reached for each of the GET requests:
        $endPoints = ["reviews", "follows", "orders", "salestax", "fees", "visits"];
        // each of the endpoints refers to a graph which will be created in the Flask app
        
        $URLs = [];
        $curlHandles = [];
        $responses = [];
        for ($i = 0; $i < 6; $i++) { // produce the URLs and curl handles; and make/store request for each 
                                        // of 6 dashboard graph images
            // create and store URL:
            $URLs[] = sprintf("%s/storedashboard/%s?store=%s&year=%s", $lightsailURL, $endPoints[$i], 
                                $selectedStore, $selectedYear); // lightSail URL located in connectionData.txt
            // create and store the curl handle with URL:
            $curlHandles[] = curlHandleFactory($URLs[$i]);
            // execute get request and store the response (hopefully a graph image):
            $responses[] = curl_exec($curlHandles[$i]);
        }
        
        for ($j = 0; $j < 6; $j += 3) { // want to split graph rows betwewen 2 divs
            echo '<div class="graph-row">';
            for ($i = $j; $i < $j+3; $i++) {
                if (curl_errno($curlHandles[$i])) {
                    echo "Error: " . curl_error($curlHandles[$i]);
                } else { 
                    if (strpos($responses[$i], "\x89PNG") === 0) {
                        echo '<img class="graph-image" src="data:image/png;base64,' . 
                                base64_encode($responses[$i]) . '" alt=graphImage"' . $i . '>';
                    } else {
                        echo '<p class="error-message">' . htmlspecialchars($responses[$i]) . '</p>';
                    }
                }
                curl_close($curlHandles[$i]);
            }
            echo '</div>';
        }
    }

    ?>
</body>
</html>