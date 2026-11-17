<?php

namespace App\Services;

use App\Models\Rule;
use Illuminate\Support\Collection;

/**
 * Keyword rules: "if the description contains X, categorise it as Y and
 * assign it to Z". The first matching rule wins for each field.
 */
class RuleEngine
{
    /** @var Collection<int, Rule>|null */
    private ?Collection $rules = null;

    /** @return array{category_id: ?int, owner_id: ?int, set_owner: bool} */
    public function suggest(string $description): array
    {
        $this->rules ??= Rule::orderBy('priority')->orderBy('id')->get();

        $result = ['category_id' => null, 'owner_id' => null, 'set_owner' => false];

        foreach ($this->rules as $rule) {
            if (! $rule->matches($description)) {
                continue;
            }
            if ($result['category_id'] === null && $rule->category_id) {
                $result['category_id'] = $rule->category_id;
            }
            if (! $result['set_owner'] && $rule->set_owner) {
                $result['owner_id'] = $rule->owner_id;
                $result['set_owner'] = true;
            }
            if ($result['category_id'] !== null && $result['set_owner']) {
                break;
            }
        }

        return $result;
    }
}
