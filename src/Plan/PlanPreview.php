<?php

declare(strict_types=1);

namespace Yaup\Plan;

final class PlanPreview
{
    /** @param array<string, mixed> $plan */
    public function render(array $plan): string
    {
        $lines = ['Plan preview', '============', ''];

        $this->appendScalar($lines, 'Status', $plan['status'] ?? null);
        $this->appendScalar($lines, 'Summary', $plan['summary'] ?? null);
        $this->appendScalar($lines, 'Repository', $plan['repository'] ?? $plan['project'] ?? null);
        $this->appendScalar($lines, 'Target branch', $plan['target_branch'] ?? $plan['base_branch'] ?? null);

        $this->appendChanges($lines, $plan['changes'] ?? null);
        $this->appendListSection($lines, 'Validation', $plan['validation'] ?? null);
        $this->appendListSection($lines, 'Risks', $plan['risks'] ?? null);
        $this->appendListSection($lines, 'Deviations', $plan['deviations'] ?? null);
        $this->appendListSection($lines, 'Next steps', $plan['next_steps'] ?? null);
        $this->appendApproval($lines, $plan['approval'] ?? null);

        return rtrim(implode("\n", $lines)) . "\n";
    }

    /** @param list<string> $lines */
    private function appendScalar(array &$lines, string $label, mixed $value): void
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return;
        }

        $lines[] = sprintf('%s: %s', $label, (string) $value);
    }

    /** @param list<string> $lines */
    private function appendChanges(array &$lines, mixed $changes): void
    {
        $this->appendSectionHeader($lines, 'Intended changes');

        if (!is_array($changes) || [] === $changes) {
            $lines[] = '- No changes listed.';
            return;
        }

        foreach ($changes as $change) {
            if (!is_array($change)) {
                $lines[] = '- ' . $this->stringify($change);
                continue;
            }

            $path = $change['path'] ?? null;
            $action = $change['action'] ?? null;
            $detail = $change['detail'] ?? $change['description'] ?? null;

            $parts = [];
            if (is_scalar($path) && '' !== trim((string) $path)) {
                $parts[] = (string) $path;
            }
            if (is_scalar($action) && '' !== trim((string) $action)) {
                $parts[] = (string) $action;
            }
            if (is_scalar($detail) && '' !== trim((string) $detail)) {
                $parts[] = (string) $detail;
            }

            if ([] === $parts) {
                $lines[] = '- ' . $this->stringify($change);
                continue;
            }

            $lines[] = '- ' . implode(' - ', $parts);
        }
    }

    /** @param list<string> $lines */
    private function appendListSection(array &$lines, string $title, mixed $items): void
    {
        if (!is_array($items) || [] === $items) {
            return;
        }

        $this->appendSectionHeader($lines, $title);
        foreach ($items as $item) {
            $lines[] = '- ' . $this->stringify($item);
        }
    }

    /** @param list<string> $lines */
    private function appendApproval(array &$lines, mixed $approval): void
    {
        if (!is_array($approval) || [] === $approval) {
            return;
        }

        $this->appendSectionHeader($lines, 'Approval');
        $approved = true === ($approval['approved'] ?? null) ? 'yes' : 'no';
        $lines[] = '- approved: ' . $approved;
        $this->appendScalarListItem($lines, 'approver', $approval['approver'] ?? null);
        $this->appendScalarListItem($lines, 'approved_at', $approval['approved_at'] ?? null);
    }

    /** @param list<string> $lines */
    private function appendScalarListItem(array &$lines, string $label, mixed $value): void
    {
        if (!is_scalar($value) || '' === trim((string) $value)) {
            return;
        }

        $lines[] = sprintf('- %s: %s', $label, (string) $value);
    }

    /** @param list<string> $lines */
    private function appendSectionHeader(array &$lines, string $title): void
    {
        $lines[] = '';
        $lines[] = $title . ':';
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return get_debug_type($value);
    }
}
