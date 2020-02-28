<?php
declare(strict_types=1);

namespace App\Feedback;

final class NoFeedback implements Feedback
{
    public function info(string $message): void
    {
    }

    public function startProcess(int $total = 0): void
    {
    }

    public function advanceProcess(int $steps = 1): void
    {
    }

    public function stopProcess(): void
    {
    }
}
