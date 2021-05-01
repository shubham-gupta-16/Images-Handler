<?php
require './class.ImagesHandler.php';

$db = new mysqli('localhost', 'root', '', 'eleamapi');
try {
    $iHandler = new ImagesHandler($db, dirname(__FILE__) . '/images');
    if (isset($_POST['submit'])) {
        $iHandler->addImage('products', $_FILES['file']['tmp_name'], 46, 1, [ImagesHandler::QUALITY_MEDIUM, ImagesHandler::QUALITY_THUMBNAIL]);
    }
    $images = $iHandler->getImagesFor('products', 46);
    echo json_encode($images);
} catch (Exception $e) {
    die($e);
}

?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="file" required>
    <input type="submit" name="submit" />
</form>