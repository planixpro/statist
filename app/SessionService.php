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

    // ----------------------------------------------------------------
    // Публичный вход
    // ----------------------------------------------------------------

    public function track(array $data): void
    {
        $domain = trim((string)($data['site']       ?? ''));
        $sid    = trim((string)($data['session_id'] ?? ''));
        $event  = trim((string)($data['event']      ?? 'page_view'));
        $ip     = trim((string)($data['ip']         ?? ''));

        $this->log("track() domain={$domain} sid={$sid} event={$event} ip={$ip}");

        if ($domain === '' || $sid === '') {
            $this->log("track() aborted: missing domain or sid");
            return;
        }

        $siteId = $this->resolveSiteId($domain);

        // --- IP blacklist ---
        if ($ip !== '' && $this->isIpBlocked($ip)) {
            $this->log("track() blocked by IP blacklist ip={$ip}");
            $this->upsertBlockedSession($siteId, $sid, $data, 'blocked_ip');
            return;
        }

        // --- ASN blacklist ---
        if ($this->isAsnBlocked($data)) {
            $this->log("track() blocked by ASN blacklist sid={$sid}");
            $this->upsertBlockedSession($siteId, $sid, $data, 'blocked_asn');
            return;
        }

        // --- Real-time bot block (явные боты по UA / headless) ---
        if (BotDetector::shouldBlockRealtime($data)) {
            $this->log("track() realtime blocked sid={$sid}");
            $this->upsertBlockedSession($siteId, $sid, $data, 'realtime_bot');
            $this->autoBlockIpIfNeeded($ip, 'realtime_bot');
            return;
        }

        // --- Нормальная запись ---
        $this->upsertSession($siteId, $sid, $data);

        if ($event !== 'heartbeat') {
            $this->insertEvent($siteId, $sid, $event, $data);
            $this->incrementEvents($siteId, $sid);
        }

        $this->evaluateSession($siteId, $sid, $data);
    }

    // ----------------------------------------------------------------
    // Разрешение site_id
    // ----------------------------------------------------------------

    private function resolveSiteId(string $domain): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM sites WHERE domain = ?");
        $stmt->execute([$domain]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int)$id;
        }

        $stmt = $this->pdo->prepare("INSERT INTO sites (domain, name) VALUES (?, ?)");
        $stmt->execute([$domain, $domain]);
        $newId = (int)$this->pdo->lastInsertId();

        $this->log("resolveSiteId created site id={$newId} domain={$domain}");
        return $newId;
    }

    // ----------------------------------------------------------------
    // Upsert нормальной сессии
    // ----------------------------------------------------------------

    private function upsertSession(int $siteId, string $sid, array $data): void
    {
        $now = date('Y-m-d H:i:s');

        // При первой вставке: is_bot=0, is_valid=0 (пока не оценена), bot_score=0
        // При обновлении: обновляем только last_activity и поля, которые ещё не заполнены.
        // is_bot / is_valid / bot_score трогаем только через evaluateSession.
        $stmt = $this->pdo->prepare("
            INSERT INTO sessions
                (site_id, session_id, ip, country, country_code, city,
                 referrer, user_agent, screen, language, timezone,
                 started_at, last_activity,
                 is_valid, is_bot, bot_score, is_suspicious, blocked_reason, events_count)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                 0, 0, 0, 0, NULL, 0)
            ON DUPLICATE KEY UPDATE
                last_activity = VALUES(last_activity),
                referrer      = COALESCE(NULLIF(VALUES(referrer), ''),      referrer),
                user_agent    = COALESCE(NULLIF(VALUES(user_agent), ''),    user_agent),
                screen        = COALESCE(NULLIF(VALUES(screen), ''),        screen),
                language      = COALESCE(NULLIF(VALUES(language), ''),      language),
                timezone      = COALESCE(NULLIF(VALUES(timezone), ''),      timezone),
                country       = COALESCE(NULLIF(VALUES(country), ''),       country),
                country_code  = COALESCE(NULLIF(VALUES(country_code), ''), country_code),
                city          = COALESCE(NULLIF(VALUES(city), ''),          city)
        ");

        $stmt->execute([
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
            $now,
            $now,
        ]);
    }

    // ----------------------------------------------------------------
    // Upsert заблокированной сессии (бот / blacklist)
    // ----------------------------------------------------------------

    private function upsertBlockedSession(int $siteId, string $sid, array $data, string $reason): void
    {
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO sessions
                (site_id, session_id, ip, country, country_code, city,
                 referrer, user_agent, screen, language, timezone,
                 started_at, last_activity,
                 is_valid, is_bot, bot_score, is_suspicious, blocked_reason, events_count)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                 0, 1, 100, 0, ?, 0)
            ON DUPLICATE KEY UPDATE
                last_activity  = VALUES(last_activity),
                is_bot         = 1,
                bot_score      = 100,
                is_suspicious  = 0,
                is_valid       = 0,
                blocked_reason = VALUES(blocked_reason)
        ");

        $stmt->execute([
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
            $now,
            $now,
            $reason,
        ]);
    }

    // ----------------------------------------------------------------
    // События
    // ----------------------------------------------------------------

    private function insertEvent(int $siteId, string $sid, string $event, array $data): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO events (site_id, session_id, event_type, path, query, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $siteId,
            $sid,
            $event,
            $data['path']  ?? '/',
            $data['query'] ?? '',
            date('Y-m-d H:i:s'),
        ]);
    }

    private function incrementEvents(int $siteId, string $sid): void
    {
        $this->pdo->prepare("
            UPDATE sessions
            SET events_count = events_count + 1
            WHERE site_id = ? AND session_id = ?
        ")->execute([$siteId, $sid]);
    }

    // ----------------------------------------------------------------
    // Оценка сессии
    // ----------------------------------------------------------------

    /**
     * Вычисляет bot_score и обновляет флаги is_bot / is_valid / is_suspicious.
     *
     * Состояния:
     *  is_bot = 1, is_valid = 0  → бот (показывается во вкладке Bots)
     *  is_bot = 0, is_valid = 1  → реальный трафик (Traffic)
     *  is_bot = 0, is_valid = 0  → подозрительный или ещё не прогретая сессия
     *                              (не считается ни в трафике, ни в ботах)
     *
     * Сессия становится is_valid=1 только если:
     *  - score < 15 (не бот)
     *  - events >= 2 ИЛИ duration >= 5 (хоть какое-то взаимодействие)
     *
     * Это означает, что первый page_view не сразу даёт is_valid,
     * но heartbeat через 7 сек (events=1, duration≈7) уже даёт.
     */
    private function evaluateSession(int $siteId, string $sid, array $data): void
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM sessions
            WHERE site_id = ? AND session_id = ?
            LIMIT 1
        ");
        $stmt->execute([$siteId, $sid]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return;
        }

        // Если сессия уже была принудительно заблокирована (realtime / blacklist) — не трогаем
        if ((int)$session['is_bot'] === 1 && (int)$session['bot_score'] === 100) {
            return;
        }

        $started  = strtotime((string)$session['started_at']);
        $last     = strtotime((string)$session['last_activity']);
        $duration = max(0, $last - $started);
        $events   = (int)($session['events_count'] ?? 0);
        $ip       = trim((string)($session['ip'] ?? ''));

        $ctx = [
            'ua'           => $session['user_agent'] ?? '',
            'path'         => $data['path']          ?? '',
            'referrer'     => $session['referrer']   ?? '',
            'fp'           => $data['fp']            ?? '',
            'screen'       => $session['screen']     ?? '',
            'js'           => (int)($data['js']      ?? 0),
            'events_count' => $events,
            'duration'     => $duration,
            'provider'     => $data['provider']      ?? '',
        ];

        $score = BotDetector::score($ctx);
        $class = BotDetector::classify($score);

        // Определяем новые флаги
        $isBot        = ($class === 'bot') ? 1 : 0;
        $isSuspicious = ($class === 'suspicious') ? 1 : 0;

        // is_valid = 1 только если НЕ бот И сессия "прогрелась"
        // Прогрев: минимум 2 события ИЛИ прошло хотя бы 5 секунд
        $isValid = 0;
        if ($isBot === 0) {
            $warmedUp = ($events >= 2) || ($duration >= 5);
            $isValid  = $warmedUp ? 1 : 0;
        }

        // Если ранее сессия уже была помечена ботом (с меньшим score) — держим
        if ((int)$session['is_bot'] === 1) {
            $isBot        = 1;
            $isSuspicious = 0;
            $isValid      = 0;
            $score        = max($score, (int)$session['bot_score']);
        }

        $reason = null;
        if ($isBot) {
            $reason = 'bot_score';
        } elseif ($isSuspicious) {
            $reason = 'suspicious_score';
        }

        $this->pdo->prepare("
            UPDATE sessions
            SET is_bot        = ?,
                is_valid      = ?,
                bot_score     = ?,
                is_suspicious = ?,
                blocked_reason = ?
            WHERE site_id = ? AND session_id = ?
        ")->execute([$isBot, $isValid, $score, $isSuspicious, $reason, $siteId, $sid]);

        if ($isBot) {
            $this->autoBlockIpIfNeeded($ip, 'bot_score');
        }

        $this->log(
            "evaluateSession sid={$sid} score={$score} duration={$duration} " .
            "events={$events} class={$class} is_valid={$isValid}"
        );
    }

    // ----------------------------------------------------------------
    // Авто-блокировка IP
    // ----------------------------------------------------------------

    private function autoBlockIpIfNeeded(string $ip, string $reason): void
    {
        if ($ip === '' || $this->isIpBlocked($ip)) {
            return;
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM sessions
            WHERE ip = ?
              AND is_bot = 1
              AND started_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)
        ");
        $stmt->execute([$ip]);
        $badCount = (int)$stmt->fetchColumn();

        if ($badCount >= 10) {
            $this->pdo->prepare("
                INSERT INTO blocked_ips (ip, reason, source, is_active)
                VALUES (?, ?, 'auto', 1)
                ON DUPLICATE KEY UPDATE is_active = 1, reason = VALUES(reason)
            ")->execute([$ip, $reason]);

            $this->log("autoBlockIpIfNeeded blocked ip={$ip} badCount={$badCount}");
        }
    }

    // ----------------------------------------------------------------
    // Проверки blacklist
    // ----------------------------------------------------------------

    private function isIpBlocked(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1 FROM blocked_ips
            WHERE ip = ?
              AND is_active = 1
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
        ");
        $stmt->execute([$ip]);
        return (bool)$stmt->fetchColumn();
    }

    private function isAsnBlocked(array $data): bool
    {
        $asn = trim((string)($data['asn'] ?? ''));
        if ($asn === '') {
            return false;
        }

        $stmt = $this->pdo->prepare("
            SELECT 1 FROM blocked_asns
            WHERE asn = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$asn]);
        return (bool)$stmt->fetchColumn();
    }

    // ----------------------------------------------------------------
    // Логирование
    // ----------------------------------------------------------------

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
