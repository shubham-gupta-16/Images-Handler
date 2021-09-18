<?php

use ImageHandler\NewImage;

require './PHPImageHandler.php';

$db = new mysqli('localhost', 'root', '', 'krishi');
try {
    $iHandler = new PHPImageHandler($db, dirname(__FILE__) . '/images');
    if (isset($_POST['submit'])) {
        $newImage = NewImage::for("table", 45)->setFile($_FILES['file']['tmp_name']);
        $iHandler->addImage($newImage);
    }
    $images = $iHandler->getImagesFor('table', 45);
    echo json_encode($images);
} catch (Exception $e) {
    die($e);
}

?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <input type="submit" name="submit" />
</form>