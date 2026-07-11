<?php

namespace Mdiki;

class ShareManager
{
    private $dataFile;
    private $lockTtl;
    private $viewerTtl;

    public function __construct($dataDir, $lockTtl = 120)
    {
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->dataFile = rtrim($dataDir, '/') . '/edit_links.json';
        $this->lockTtl = $lockTtl;
        $this->viewerTtl = max($lockTtl + 30, 90);
    }

    public function getOrCreateToken($path)
    {
        $path = Utils::sanitizePath($path);
        return $this->withData(function (&$data) use ($path) {
            foreach ($data['links'] as $token => $item) {
                if (($item['path'] ?? '') === $path) {
                    return $token;
                }
            }

            do {
                $token = bin2hex(random_bytes(32));
            } while (isset($data['links'][$token]));

            $data['links'][$token] = [
                'path' => $path,
                'created_at' => time(),
            ];

            return $token;
        });
    }

    public function resolveToken($token)
    {
        $token = $this->normalizeToken($token);
        if ($token === '') {
            return null;
        }

        return $this->withData(function (&$data) use ($token) {
            return $data['links'][$token]['path'] ?? null;
        }, false);
    }

    public function acquireLock($token, $owner)
    {
        $token = $this->normalizeToken($token);
        return $this->withData(function (&$data) use ($token, $owner) {
            $this->cleanupPresence($data);
            if (!isset($data['links'][$token])) {
                return false;
            }

            $lock = $data['locks'][$token] ?? null;
            if ($lock && ($lock['owner'] ?? '') !== $owner && ($lock['expires_at'] ?? 0) > time()) {
                return false;
            }

            $data['locks'][$token] = [
                'owner' => $owner,
                'expires_at' => time() + $this->lockTtl,
            ];

            return true;
        });
    }

    public function refreshLock($token, $owner)
    {
        return $this->acquireLock($token, $owner);
    }

    public function ownsLock($token, $owner)
    {
        $token = $this->normalizeToken($token);
        return $this->withData(function (&$data) use ($token, $owner) {
            $this->cleanupPresence($data);
            $lock = $data['locks'][$token] ?? null;
            return $lock && ($lock['owner'] ?? '') === $owner && ($lock['expires_at'] ?? 0) > time();
        });
    }

    public function releaseLock($token, $owner)
    {
        $token = $this->normalizeToken($token);
        return $this->withData(function (&$data) use ($token, $owner) {
            $lock = $data['locks'][$token] ?? null;
            unset($data['viewers'][$token][$owner]);
            if (empty($data['viewers'][$token])) {
                unset($data['viewers'][$token]);
            }
            if ($lock && ($lock['owner'] ?? '') === $owner) {
                unset($data['locks'][$token]);
                return true;
            }
            return true;
        });
    }

    public function heartbeat($token, $owner, $wantsLock)
    {
        $token = $this->normalizeToken($token);
        return $this->withData(function (&$data) use ($token, $owner, $wantsLock) {
            $this->cleanupPresence($data);
            if (!isset($data['links'][$token])) {
                return [
                    'valid' => false,
                    'viewer_count' => 0,
                    'can_edit' => false,
                    'has_editor' => false,
                ];
            }

            $data['viewers'][$token] = $data['viewers'][$token] ?? [];
            $data['viewers'][$token][$owner] = [
                'last_seen' => time(),
            ];

            if (!$wantsLock) {
                $lock = $data['locks'][$token] ?? null;
                if ($lock && ($lock['owner'] ?? '') === $owner) {
                    unset($data['locks'][$token]);
                }
            } else {
                $lock = $data['locks'][$token] ?? null;
                if (!$lock || ($lock['owner'] ?? '') === $owner || ($lock['expires_at'] ?? 0) <= time()) {
                    $data['locks'][$token] = [
                        'owner' => $owner,
                        'expires_at' => time() + $this->lockTtl,
                    ];
                }
            }

            $lock = $data['locks'][$token] ?? null;
            $hasEditor = $lock && ($lock['expires_at'] ?? 0) > time();
            $canEdit = $hasEditor && ($lock['owner'] ?? '') === $owner;

            return [
                'valid' => true,
                'viewer_count' => count($data['viewers'][$token] ?? []),
                'can_edit' => $canEdit,
                'has_editor' => $hasEditor,
            ];
        });
    }

    private function withData($callback, $write = true)
    {
        $handle = fopen($this->dataFile, 'c+');
        if (!$handle) {
            throw new \Exception('Failed to open share data file');
        }

        try {
            flock($handle, LOCK_EX);
            rewind($handle);
            $raw = stream_get_contents($handle);
            $data = json_decode($raw ?: '', true);
            if (!is_array($data)) {
                $data = ['links' => [], 'locks' => [], 'viewers' => []];
            }
            $data['links'] = $data['links'] ?? [];
            $data['locks'] = $data['locks'] ?? [];
            $data['viewers'] = $data['viewers'] ?? [];

            $result = $callback($data);

            if ($write) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            flock($handle, LOCK_UN);
            fclose($handle);
            return $result;
        } catch (\Throwable $e) {
            flock($handle, LOCK_UN);
            fclose($handle);
            throw $e;
        }
    }

    private function cleanupPresence(&$data)
    {
        $now = time();
        foreach ($data['locks'] as $token => $lock) {
            if (($lock['expires_at'] ?? 0) <= $now) {
                unset($data['locks'][$token]);
            }
        }
        foreach ($data['viewers'] as $token => $viewers) {
            foreach ($viewers as $owner => $viewer) {
                if (($viewer['last_seen'] ?? 0) + $this->viewerTtl <= $now) {
                    unset($data['viewers'][$token][$owner]);
                }
            }
            if (empty($data['viewers'][$token])) {
                unset($data['viewers'][$token]);
            }
        }
    }

    private function normalizeToken($token)
    {
        $token = (string)$token;
        return preg_match('/^[a-f0-9]{64}$/', $token) ? $token : '';
    }
}
