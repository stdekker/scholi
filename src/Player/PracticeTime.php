<?php

declare(strict_types=1);

namespace App\Player;

/**
 * How long a pupil practised: the seconds played in finished rounds, kept per day for a rolling
 * window of the last WINDOW_DAYS days (today and the days before), shown in whole minutes.
 *
 * Days are calendar days in Dutch time, whatever the server's timezone.
 */
final class PracticeTime
{
    public const int WINDOW_DAYS = 7;
    /** Longest round that counts; matches the longest round a result may report. */
    public const int MAX_ROUND_SECONDS = 3_600;
    /** A day can't hold more seconds than this. */
    public const int MAX_DAY_SECONDS = 86_400;
    private const string TIMEZONE = 'Europe/Amsterdam';

    public static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone(self::TIMEZONE));
    }

    /**
     * @param string $day Y-m-d, as the guest cookie keeps it
     */
    public static function day(string $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day, new \DateTimeZone(self::TIMEZONE));
    }

    /** The first day that still counts. */
    public static function windowStart(): \DateTimeImmutable
    {
        return self::today()->modify(\sprintf('-%d days', self::WINDOW_DAYS - 1));
    }

    public static function minutes(int $seconds): int
    {
        return intdiv(max(0, $seconds), 60);
    }
}
