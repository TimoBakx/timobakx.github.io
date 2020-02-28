<?php
declare(strict_types=1);

namespace App\Feedback;

interface Feedback
{
    public function info(string $message): void;
    public function startProgress(int $total = 0): void;
    public function advanceProgress(int $steps = 1): void;
    public function stopProgress(): void;
}
