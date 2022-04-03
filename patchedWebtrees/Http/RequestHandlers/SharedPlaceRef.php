<?php

namespace Cissee\WebtreesExt\Http\RequestHandlers;

use Cissee\WebtreesExt\SharedPlace;

class SharedPlaceRef {

    private $record;
    private $existed;
    private $created;
    private $parent;

    public function record(): SharedPlace {
        return $this->record;
    }

    public function existed(): bool {
        return $this->existed;
    }

    public function created(): int {
        return $this->created;
    }

    public function parent(): ?SharedPlaceRef {
        return $this->parent;
    }

    public function __construct(
        SharedPlace $record,
        bool $existed,
        int $created,
        ?SharedPlaceRef $parent) {

        $this->record = $record;
        $this->existed = $existed;
        $this->created = $created;
        $this->parent = $parent;
    }

}
