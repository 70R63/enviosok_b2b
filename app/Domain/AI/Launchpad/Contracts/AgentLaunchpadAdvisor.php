<?php
namespace App\Domain\AI\Launchpad\Contracts;
use App\Domain\AI\Launchpad\Data\{LaunchpadIntakeData,LaunchpadRecommendationData};
interface AgentLaunchpadAdvisor { public function recommend(LaunchpadIntakeData $intake):LaunchpadRecommendationData; }
