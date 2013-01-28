<?php

include('library/RestClient.php');

$test = new Test_Remote_Model();
print_r($test->get());
