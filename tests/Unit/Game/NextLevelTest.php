<?php

declare(strict_types=1);

namespace App\Tests\Unit\Game;

use App\Game\DicteeLevel;
use App\Game\ErbijErafLevel;
use App\Game\ErbijErafOperation;
use App\Game\TafelsMix;
use PHPUnit\Framework\TestCase;

final class NextLevelTest extends TestCase
{
    public function testMixesGoFromFewToAllTables(): void
    {
        self::assertSame(TafelsMix::TwoToTen, TafelsMix::TwoToFive->next());
        self::assertSame(TafelsMix::TwoToTwelve, TafelsMix::TwoToTen->next());
        self::assertNull(TafelsMix::TwoToTwelve->next());
    }

    public function testErbijErafLevelsGoUpOneByOne(): void
    {
        self::assertSame(ErbijErafLevel::Tot20, ErbijErafLevel::Tot10->next());
        self::assertSame(ErbijErafLevel::Tot100Brug, ErbijErafLevel::Tot100->next());
        self::assertNull(ErbijErafLevel::Tot100Brug->next());
    }

    public function testOperationsGoFromErbijToDoorElkaar(): void
    {
        self::assertSame(ErbijErafOperation::Eraf, ErbijErafOperation::Erbij->next());
        self::assertSame(ErbijErafOperation::DoorElkaar, ErbijErafOperation::Eraf->next());
        self::assertNull(ErbijErafOperation::DoorElkaar->next());
    }

    public function testDicteeLevelsShowLessAndLessOfTheWord(): void
    {
        self::assertSame(DicteeLevel::Partial, DicteeLevel::Copy->next());
        self::assertSame(DicteeLevel::Blanks, DicteeLevel::Partial->next());
        self::assertSame(DicteeLevel::Hidden, DicteeLevel::Blanks->next());
        self::assertNull(DicteeLevel::Hidden->next());
    }
}
