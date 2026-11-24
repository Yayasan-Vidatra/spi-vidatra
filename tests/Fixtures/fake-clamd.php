<?php

/**
 * clamd palsu untuk ClamAvScannerTest: menerima satu koneksi INSTREAM, membaca seluruh potongan
 * data, lalu membalas teks dari argumen pertama diakhiri "\0". Baris pertama keluaran adalah alamatnya.
 *
 * Pemakaian: php fake-clamd.php "stream: OK"
 */
$reply = $argv[1] ?? 'stream: OK';
$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

if ($server === false) {
    fwrite(STDERR, $errorMessage);
    exit(1);
}

echo stream_socket_get_name($server, false), PHP_EOL;

$connection = stream_socket_accept($server, 10);

if ($connection === false) {
    exit(1);
}

$readExactly = function (int $length) use ($connection): string {
    $data = '';

    while (strlen($data) < $length && ! feof($connection)) {
        $data .= fread($connection, $length - strlen($data));
    }

    return $data;
};

$command = $readExactly(strlen("zINSTREAM\0"));

if ($command === "zINSTREAM\0") {
    while (true) {
        $header = $readExactly(4);

        if (strlen($header) < 4) {
            break;
        }

        $length = unpack('N', $header)[1];

        if ($length === 0) {
            break;
        }

        $readExactly($length);
    }
}

fwrite($connection, $reply."\0");
fclose($connection);
fclose($server);
