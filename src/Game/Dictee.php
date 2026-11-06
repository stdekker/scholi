<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Rules of dictee that the server needs to know: the words of each groep, how a round is stored
 * and its time limit. A round's ten words are picked in assets/games/taal/dictee.js.
 *
 * A round is stored under groep × 10 + level, e.g. 83 = groep 8, streepjes.
 *
 * The words are spoken by recordings in assets/audio/taal/dictee/<word>.mp3, made by
 * bin/generate-dictee-audio.mjs from the same lists (dictee_words.json is its source).
 */
final class Dictee
{
    public const string WORDS_FILE = __DIR__.'/dictee_words.json';

    /** Where the recordings live, relative to the AssetMapper root (assets/). */
    public const string AUDIO_DIR = 'audio/taal/dictee';

    /** The longest answer the input accepts: the longest word is 16 letters. */
    public const int ANSWER_MAX_LENGTH = 20;

    /**
     * The words of a groep.
     *
     * @return list<non-empty-string>
     */
    public static function words(DicteeGroup $group): array
    {
        $lists = json_decode((string) file_get_contents(self::WORDS_FILE), true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($lists) && \is_array($lists[$group->value] ?? null));

        return array_values(array_map(static function (mixed $word): string {
            \assert(\is_string($word) && 1 === preg_match('/^[a-z]+$/', $word));

            return $word;
        }, $lists[$group->value]));
    }

    /** The logical AssetMapper path of a word's recording. */
    public static function audioPath(string $word): string
    {
        return \sprintf('%s/%s.mp3', self::AUDIO_DIR, $word);
    }

    /**
     * Every round a pupil can pick, per level, with the number it is stored under.
     *
     * @return list<array{level: DicteeLevel, rounds: list<array{group: DicteeGroup, stored: int}>}>
     */
    public static function grid(): array
    {
        return array_map(
            static fn (DicteeLevel $level): array => [
                'level' => $level,
                'rounds' => array_map(
                    static fn (DicteeGroup $group): array => ['group' => $group, 'stored' => self::storedLevel($group, $level)],
                    DicteeGroup::cases(),
                ),
            ],
            DicteeLevel::cases(),
        );
    }

    public static function storedLevel(DicteeGroup $group, DicteeLevel $level): int
    {
        return $group->value * 10 + $level->value;
    }

    /**
     * @return array{DicteeGroup, DicteeLevel}|null
     */
    public static function fromStoredLevel(int $stored): ?array
    {
        $group = DicteeGroup::tryFrom(intdiv($stored, 10));
        $level = DicteeLevel::tryFrom($stored % 10);

        return null === $group || null === $level ? null : [$group, $level];
    }

    public static function isKnown(int $stored): bool
    {
        return null !== self::fromStoredLevel($stored);
    }

    public static function timeLimitMs(int $stored): int
    {
        $round = self::fromStoredLevel($stored);

        return null === $round ? RoundTime::THREE_MINUTES_MS : $round[1]->timeLimitMs();
    }
}
