<?php

require_once __DIR__ . '/../app/BotDetector.php';

class SessionService
{
    private PDO    $pdo;
    private string $logFile;

    public function __construct(PDO $pdo)
    {
        $this->pdo     = $pdo;
        $this->logFile = __DIR__ . '/../storage/logs/statist.log';
    }

    // -------------------------------------------------------

    public function track(array $data): void
    {
        $domain = $data['site']       ?? null;
        $sid    = $data['session_id'] ?? null;
        $event  = $data['event']      ?? 'page_view';

        $this->log("track() domain=$domain sid=$sid event=$event");

        if (!$domain || !$sid) {
            $this->log("track() aborted — missing domain or sid");
            return;
        }

        $siteId    = $this->resolveSiteId($domain);
        $heartbeat = ($event === 'heartbeat') ? 1 : 0;

        $this->log("siteId=$siteId heartbeat=$heartbeat");

        $this->upsertSession($siteId, $sid, $data, $heartbeat);

        // On session_end — run suspicious-session check and flag if needed
        if ($event === 'session_end') {
            $this->evaluateBotFlag($siteId, $sid);
        }

        if ($event !== 'heartbeat') {
            $this->insertEvent($siteId, $sid, $event, $data);
        }
    }

    // -------------------------------------------------------

    private function resolveSiteId(string $domain): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sites WHERE domain = ?");
        $stmt->execute([$domain]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $stmt = $this->pdo->prepare("INSERT INTO sites (domain, name) VALUES (?, ?)");
        $stmt->execute([$domain, $domain]);
        $newId = (int) $this->pdo->lastInsertId();

        $this->log("resolveSiteId — created new site id=$newId for domain=$domain");

        return $newId;
    }

    // -------------------------------------------------------

    private function upsertSession(
        int    $siteId,
        string $sid,
        array  $data,
        int    $heartbeat
    ): void {
        $now = date('Y-m-d H:i:s');

        $params = [
            $siteId,
            $sid,
            $data['ip']           ?? null,
            $data['country']      ?? null,
            $data['country_code'] ?? null,
            $data['city']         ?? null,
            $data['referrer']     ?? null,
            $data['ua']           ?? null,
            $data['screen']       ?? null,
            $data['lang']         ?? null,
            $data['tz']           ?? null,
            $now,        // started_at
            $now,        // last_activity (INSERT)
            $heartbeat,  // is_valid
            $now,        // last_activity (UPDATE)
            $heartbeat,  // IF(? = 1 ...) в UPDATE
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO sessions
                (site_id, session_id, ip, country, country_code, city,
                 referrer, user_agent, screen, language, timezone,
                 started_at, last_activity, is_valid)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                last_activity = ?,
                is_valid      = IF(? = 1, 1, is_valid)
        ");

        $stmt->execute($params);

        $this->log("upsertSession rowCount=" . $stmt->rowCount());
    }

    // -------------------------------------------------------
    // Bot flagging — runs after session_end event
    // -------------------------------------------------------

    private function evaluateBotFlag(int $siteId, string $sid): void
    {
        $stmt = $this->pdo->prepare("
            SELECT started_at, last_activity, is_valid, ip
            FROM sessions
            WHERE site_id = ? AND session_id = ?
            LIMIT 1
        ");
        $stmt->execute([$siteId, $sid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return;
        }

        if (BotDetector::isSuspiciousSession($row)) {
            $upd = $this->pdo->prepare("
                UPDATE sessions SET is_bot = 1
                WHERE site_id = ? AND session_id = ?
            ");
            $upd->execute([$siteId, $sid]);
            $this->log("evaluateBotFlag — flagged as bot: sid=$sid");
        }
    }

    // -------------------------------------------------------

    private function insertEvent(
        int    $siteId,
        string $sid,
        string $event,
        array  $data
    ): void {
        $params = [
            $siteId,
            $sid,
            $event,
            $data['path']  ?? '/',
            $data['query'] ?? '',
            date('Y-m-d H:i:s'),
        ];

        $stmt = $this->pdo->prepare("
            INSERT INTO events
                (site_id, session_id, event_type, path, query, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute($params);

        $this->log("insertEvent event=$event rowCount=" . $stmt->rowCount());
    }

    // -------------------------------------------------------

    private function log(string $msg): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->logFile,
            date('Y-m-d H:i:s') . ' ' . $msg . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
