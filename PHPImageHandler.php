<?php
/*
PHPImageHandler
Version: 0.9.4 Beta
Developer: Shubham Gupta
Licence: MIT
Last Updated: 18 Sep, 2021 at 8:17 PM UTC +5:30
*/

namespace {

    use ImageHandler\NewImage;

    class PHPImageHandler
    {

        private const ERROR_MYSQLI_QUERY_MSG = 'Error in mysqli query';
        private const ERROR_MYSQLI_CONNECT_MSG = 'Error in mysqli connection';
        public const ERROR_CODE = 50;
        public const QUALITY_MINI = 6;
        public const QUALITY_THUMBNAIL = 7;
        public const QUALITY_MEDIUM = 8;
        public const QUALITY_ORIGINAL = 9;
        public const MAX_SUPPORT_RES = 1920;

        public function __construct(mysqli $db, string $imagesDIR)
        {
            if ($db->connect_errno) {
                throw new Exception(self::ERROR_MYSQLI_CONNECT_MSG, self::ERROR_CODE);
            }
            $db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, TRUE);
            $this->db = $db;
            $this->imagesDIR = $imagesDIR . '/';

            $this->initialize();
        }
        public function initialize()
        {
            $q = "CREATE TABLE IF NOT EXISTS `all_images` (
            `imageID` INT(11) NOT NULL ,
            `name` VARCHAR(255) NOT NULL ,
            `data` JSON NULL ,
            PRIMARY KEY (`imageID`)
            ) ";

            $aq = "CREATE TABLE IF NOT EXISTS `assinged_images` (
            `uniqueID` INT(11) NOT NULL AUTO_INCREMENT,
            `imageID` INT(11) NOT NULL ,
            `for` INT(11) NOT NULL default 0 ,
            `position` INT(11) NOT NULL default 0 ,
            `table` VARCHAR(255) NOT NULL ,
            PRIMARY KEY (`uniqueID`)
            ) ";

            if (!$this->db->query($q)) {
                throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_CODE);
            }
            if (!$this->db->query($aq)) {
                throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_CODE);
            }
        }

        public function getImagesFor(string $dir, $for)
        {
            $q = "SELECT uniqueID, position, dir, name, data FROM `assinged_images`,`all_images`  WHERE `assinged_images`.`imageID` = `all_images`.`imageID`
        AND `assinged_images`.`dir` = '$dir' AND `assinged_images`.`for` = $for";
            $res = $this->db->query($q);
            if (!$res) {
                throw new Exception($q, self::ERROR_CODE);
            }
            $array = [];
            while ($row = $res->fetch_assoc()) {
                $row['data'] = (array) json_decode($row['data']);
                $qualities = $row['data']['qualities'];
                $available = $row['data']['available'];

                $qImages = [];
                $avl = [];
                foreach ($qualities as $qlt) {
                    $subDir = "";
                    $qImages[] = $subDir;
                    if (in_array($qlt, $available))
                        $avl[] = $subDir;
                    /* 
                if (in_array($qlt, $available)) {
                    $qImages[$subDir] = $subDir . '/' . $row['name'];
                } else {
                    $qImages[$subDir] = $this->_getQualtityDirName(self::QUALITY_ORIGINAL) . '/' . $row['name'];
                } */
                }
                $row[] = $qImages;
                $array[] = [
                    'id' => $row['uniqueID'],
                    'pos' => $row['position'],
                    'dir' => $row['dir'],
                    'name' => $row['name'],
                    'keywords' => $row['data']['keywords'],
                    'qualities' => $qImages,
                    'available' => $avl,
                ];
            }
            return $array;
        }
        // $fileName = $this->_saveImage($file, $fileName, $dir . '/' . $this->_getQualtityDirName(self::QUALITY_ORIGINAL), -1);
        // $this->_saveImage($this->imagesDIR . $dir . '/' . $this->_getQualtityDirName(self::QUALITY_ORIGINAL) . '/' . $fileName, $fileName, $dir . '/' . $this->_getQualtityDirName($quality), $this->_getQualtityMaxRes($quality)))

        // string $dir, string $file, $for, int $position, array $moreQualities = null, string $keywords = null
        public function addImage(NewImage $nImage)
        {
            $nextID = $this->_getNextImageID();
            $randomName = $nextID . '_' . $this->_randomStr(4);


            $imageLocation = $this->_getTargetDir($nImage->table . '/original');
            $fileName = $this->_saveImage($nImage->file, $randomName, $imageLocation, $nImage->px);

            $qltArr = ['original'];
            $moreRes = $nImage->res;
            foreach ($moreRes as $name => $maxRes) {
                if ($this->_saveImage($imageLocation . $fileName, $fileName, $this->_getTargetDir($nImage->table . '/' . $name), $maxRes))
                    $qltArr[] = $name;
            }

            $moreQualities[] = self::QUALITY_ORIGINAL;
            $data = json_encode([
                'resolutions' => $qltArr,
                'keywords' => $nImage->keywords,
            ]);

            $q = "INSERT INTO `all_images` (`imageID`, `name`, `data`) VALUES
        ($nextID, '$fileName', '$data')";
            if (!$this->db->query($q)) {
                throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_CODE);
            }

            $for = $nImage->uniqueKey;
            $table = $nImage->table;
            // $dir = $this->db->real_escape_string($dir);
            //TODO dynamic position
            $q2 = "INSERT INTO `assinged_images` (`imageID`, `for`, `position`, `table`) VALUES
        ($nextID, $for, 1, '$table')";
            if (!$this->db->query($q2)) {
                throw new Exception($q2, self::ERROR_CODE);
            }
        }
        private function _getTargetDir(string $subDIR): string
        {
            $target_dir = $this->imagesDIR . $subDIR . '/';
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            return $target_dir;
        }
        private function _saveImage(string $file, string $fileName, string $target_dir, int $maxRes)
        {
            $data = getimagesize($file);
            $fileName = $fileName . $this->_getFileExtension($data['mime']);
            if (!$data) {
                throw new Exception("****************t", self::ERROR_CODE);
            }

            if (file_exists($target_dir . $fileName)) {
                unlink($target_dir . $fileName);
            }

            list($width, $height) = $data;

            list($nwidth, $nheight) = $this->_getNewWidthAndHeight($width, $height, $maxRes);
            if ($nwidth > $width)
                return null;

            $newimage = imagecreatetruecolor($nwidth, $nheight);
            if ($data['mime'] === 'image/jpeg') {
                $source = imagecreatefromjpeg($file);
                imagecopyresized($newimage, $source, 0, 0, 0, 0, $nwidth, $nheight, $width, $height);
                imagejpeg($newimage, $target_dir . $fileName);
            } elseif ($data['mime'] === 'image/png') {
                $source = imagecreatefrompng($file);
                imagecopyresized($newimage, $source, 0, 0, 0, 0, $nwidth, $nheight, $width, $height);
                imagepng($newimage, $target_dir . $fileName);
            } elseif ($data['mime'] === 'image/gif') {
                $source = imagecreatefromgif($file);
                imagecopyresized($newimage, $source, 0, 0, 0, 0, $nwidth, $nheight, $width, $height);
                imagegif($newimage, $target_dir . $fileName);
            } else {
                // todo fix error message
                throw new Exception(self::ERROR_CODE, self::ERROR_CODE);
            }

            return $fileName;
        }

       

        private function _getNextImageID()
        {
            $qForID = "SELECT max(imageID) from all_images";
            $idRes = $this->db->query($qForID);
            if (!$idRes) {
                throw new Exception(self::ERROR_MYSQLI_QUERY_MSG, self::ERROR_CODE);
            }
            $nextID = $idRes->fetch_array()[0];
            return ($nextID == null) ? 1 : $nextID + 1;
        }

        private function _randomStr(int $length)
        {
            return bin2hex(openssl_random_pseudo_bytes($length));
        }

        private function _getFileExtension(string $mime)
        {
            if ($mime === 'image/jpeg') {
                return '.jpg';
            } elseif ($mime === 'image/png') {
                return '.png';
            } elseif ($mime === 'image/gif') {
                return '.gif';
            }
            return '.tmp';
        }

        private function _getNewWidthAndHeight(int $width, int $height, int $maxRes)
        {
            $w = 0;
            $h = 0;
            if ($maxRes == -1) {
                if ($height > self::MAX_SUPPORT_RES || $width > self::MAX_SUPPORT_RES) {
                    $maxRes = self::MAX_SUPPORT_RES;
                } else {
                    return [$width, $height];
                }
            }
            if ($width > $height) {
                $w = $maxRes;
                $h = intval($height * $maxRes / $width);
            } elseif ($height > $width) {
                $h = $maxRes;
                $w = intval($width * $maxRes / $height);
            } else {
                $w = $maxRes;
                $h = $maxRes;
            }
            return [$w, $h];
        }
    }
}

namespace ImageHandler {

    class Quality
    {
        public function __construct(string $qualityName, int $resolution)
        {
            $this->dir = $qualityName;
            $this->res = $resolution;

            // $d = (new NewImage("products", 45))->setMoreResolutions();
            // $d->setMoreResolutions(['original' => 1080], []);
        }
    }


    class NewImage
    {
        public static function for(string $table, string $uniqueKey): NewImage
        {
            return new NewImage($table, $uniqueKey);
        }
        public function setOriginalResolution(int $px = 1920): NewImage
        {
            $this->px = $px;
            return $this;
        }

        public function setMoreResolutions(array ...$res): NewImage
        {
            $this->res = $res;
            return $this;
        }
        public function setFile(int $file): NewImage
        {
            $this->file = $file;
            return $this;
        }
        public function setKeyWords(string $keywords): NewImage
        {
            $this->keywords = $keywords;
            return $this;
        }
        public function __construct(string $table, string $uniqueKey)
        {
            $this->table = $table;
            $this->res = [];
            $this->file = null;
            $this->keywords = null;
            $this->uniqueKey = $uniqueKey;
        }
    }
}
