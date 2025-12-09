<?php
$extra_css = ['pesan.css'];
require_once "../includes/functions.php";
require_once "../includes/auth.php";

$conn = getDBConnection();
$active_page = 'pesan';
$page_title  = 'Pesan & Permintaan';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id']);

pg_query_params($conn,
    "DELETE FROM pesan_kontak WHERE id_pesan = $1",
    [$id]
);

header("Location: index.php?deleted=1");
exit;
