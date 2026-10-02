<?php

// Quien escriba /super/ en el navegador llega al login
// (o a la agenda, si ya inició sesión).
header("Location: login.php");

exit;
