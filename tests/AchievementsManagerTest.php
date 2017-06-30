<?php

namespace Laravel\Achievements\Tests;

use Illuminate\Support\Facades\Event;
use Laravel\Achievements\AchievementsStorage;
use Laravel\Achievements\Events\AchievementsCompleted;
use Laravel\Achievements\Tests\Stubs\User;
use Zurbaev\Achievements\AchievementCriteriaChange;
use Zurbaev\Achievements\AchievementsManager;

class AchievementsManagerTest extends TestCase
{
    /**
     * @var User
     */
    protected $user;

    /**
     * @var AchievementsManager
     */
    protected $manager;

    public function setUp()
    {
        parent::setUp();

        $this->seedAchievements();
        $this->user = User::create(['name' => 'John Doe']);
        $this->manager = new AchievementsManager(new AchievementsStorage());
    }

    public function testCriteriaUpdated()
    {
        AchievementsManager::registerHandler('reach_level', function () {
            return new AchievementCriteriaChange(1, AchievementCriteriaChange::PROGRESS_ACCUMULATE);
        });

        $this->assertSame(0, count($this->user->achievementCriterias));

        $count = $this->manager->updateAchievementCriteria(
            $this->user, 'reach_level', [
                'value' => 5,
            ]
        );

        $this->assertSame(3, $count);
        $this->assertSame(3, count($this->user->fresh()->achievementCriterias));
    }

    public function testAchievementCompleted()
    {
        Event::fake();

        AchievementsManager::registerHandler('reach_level', function ($owner, $criteria, $achievement, $data) {
            return new AchievementCriteriaChange(intval($data['value'] ?? 1), AchievementCriteriaChange::PROGRESS_ACCUMULATE);
        });

        $this->assertSame(0, count($this->user->achievements));
        $this->assertSame(0, $this->user->achievementPoints());

        $count = $this->manager->updateAchievementCriteria(
            $this->user, 'reach_level', [
                'value' => 10,
            ]
        );

        Event::assertDispatched(AchievementsCompleted::class);

        $this->assertSame(3, $count);

        $this->user = $this->user->fresh();
        $this->assertSame(1, count($this->user->achievements));
        $this->assertSame(10, $this->user->achievementPoints());
    }
}