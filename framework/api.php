<?php
// framework/api.php

function input(): array
{
  $data = [];

  if (!empty($_GET))  $data = array_merge($data, $_GET);
  if (!empty($_POST)) $data = array_merge($data, $_POST);

  $raw = file_get_contents('php://input');
  if ($raw) {
    $json = json_decode($raw, true);
    if (is_array($json)) $data = array_merge($data, $json);
  }

  return $data;
}

function fileInput(?string $key = null)
{
  if ($key === null) return $_FILES ?? [];
  return $_FILES[$key] ?? null;
}

function output($data = null, int $status = 200): void
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
  exit;
}

function error(string $code, string $message, int $status = 400, $details = null): void
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => ['code'=>$code,'message'=>$message,'details'=>$details]], JSON_UNESCAPED_UNICODE);
  exit;
}
