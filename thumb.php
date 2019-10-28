<?php

/* * ***************************************************************
  Desarrollado por Cubo3

  Luis Gonzalez ==> lg@cubo3.cl


 * **************************************************************** */

$_GET['ancho'] = $_GET['width'];
$_GET['alto'] = $_GET['height'];
$archivo = $_GET['pic'];
$exte = explode('.', $archivo);
$cu = count($exte);
$exte = $exte[$cu - 1];
if ($exte != 'JPG' && $exte != 'jpg' && $exte != 'PNG' && $exte != 'png' && $exte != 'GIF' && $exte != 'gif' && $exte != 'JPEG' && $exte != 'jpeg') {
    ?>
    <script>
        alert('Tipo no permitido!!!');
    </script>
    <?php

    exit();
}
//header('Content-type: image/'.$exte);
header('Content-type: image/jpeg, image/jpg, image/gif, image/png');

list($width, $height) = getimagesize($archivo);
if (isset($_GET['ancho']) && $_GET['ancho'] <= $width && !isset($_GET['alto'])) {
    $prop = $width / $_GET['ancho'];
}
elseif (isset($_GET['alto']) && $_GET['alto'] <= $height && !isset($_GET['ancho'])) {
    $prop = $height / $_GET['alto'];
}
else {
    $prop = 1;
}

$newwidth = $width / $prop;
$newheight = $height / $prop;

//$thumb = imagecreate($newwidth, $newheight);
$thumb = imagecreatetruecolor($newwidth, $newheight);
if ($exte == 'jpg' || $exte == 'JPG' || $exte == 'jpeg' || $exte == 'JPEG') {
    $source = imagecreatefromjpeg($archivo);
    imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    imagejpeg($thumb, '', 100);
}
elseif ($exte == 'png' || $exte == 'PNG') {
    $source = imagecreatefrompng($archivo);
    imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    imagepng($thumb, '', 100);
}
elseif ($exte == 'gif' || $exte == 'GIF') {
    $source = imagecreatefromgif($archivo);
    imagecopyresized($thumb, $source, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
    imagegif($thumb);
}
imagedestroy($thumb);
?>