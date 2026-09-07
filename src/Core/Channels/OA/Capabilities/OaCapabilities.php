<?php

declare(strict_types=1);

namespace FieldVn\Zalo\Core\Channels\OA\Capabilities;

use FieldVn\Zalo\Core\Channels\OA\OAChannel;

final class OaCapabilities
{
    public function __construct(
        private readonly OAChannel $oa,
        private readonly ?CapabilityRegistry $registry = null,
    ) {}

    /**
     * @param  string|OaCapability|CheckSpec|array<string, mixed>  ...$args
     */
    public function check(string|OaCapability|CheckSpec|array ...$args): CapabilityReport
    {
        $registry = $this->registry ?? new CapabilityRegistry;
        $specs = $this->normalize(array_values($args));
        $snapshot = new OaSnapshot($this->oa);
        // getoa một lần trước checkers: report.package luôn có dù chỉ hỏi ZBS.
        $snapshot->oaInfo();
        $items = [];

        foreach ($specs as $spec) {
            $checker = $registry->get($spec->key);

            if ($checker === null) {
                $items[$spec->key] = new CapabilityResult(
                    key: $spec->key,
                    available: false,
                    hint: "Không có checker `{$spec->key}`. Dùng CapabilityRegistry::extend().",
                    source: 'none',
                );

                continue;
            }

            $items[$spec->key] = $checker->check($snapshot, $spec->options);
        }

        $info = $snapshot->oaInfo();

        return new CapabilityReport($items, [
            'oa_id' => $info['oa_id'] ?? null,
            'name' => $info['name'] ?? null,
            'package_name' => $snapshot->packageName(),
            'linked_ZCA' => $info['linked_ZCA'] ?? $info['linked_zca'] ?? null,
            'is_verified' => $info['is_verified'] ?? null,
            'tier' => OaPackageMatrix::tier($snapshot->packageName()),
        ]);
    }

    /**
     * @param  list<string|OaCapability|CheckSpec|array<string, mixed>>  $args
     * @return list<CheckSpec>
     */
    private function normalize(array $args): array
    {
        if ($args === []) {
            return array_map(
                static fn (OaCapability $cap): CheckSpec => new CheckSpec($cap->value),
                OaCapability::defaults(),
            );
        }

        $options = [];
        $last = $args[array_key_last($args)];

        if (is_array($last) && $last !== []) {
            $options = $last;
            array_pop($args);
        }

        $specs = [];

        foreach ($args as $arg) {
            if ($arg instanceof CheckSpec) {
                $specs[] = new CheckSpec($arg->key, $arg->options + $options);

                continue;
            }

            if ($arg instanceof OaCapability) {
                $specs[] = new CheckSpec($arg->value, $options);

                continue;
            }

            if (is_string($arg)) {
                $specs[] = new CheckSpec($arg, $options);
            }
        }

        return $specs;
    }
}
