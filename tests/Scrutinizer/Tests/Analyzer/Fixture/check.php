<?php

$content = file_get_contents($argv[1]);

echo json_encode(array(
    'comments' => array(
        array(
            'line' => 1,
            'id' => 'foo',
            'message' => $content,
        )
    ),
));
