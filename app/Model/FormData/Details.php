<?php

namespace App\Model\FormData;

class Details
{
    public function __construct(
        public readonly string|null $realname,
        public readonly string|null $bio,
        public readonly string|null $bio_fi,
    ) {
    }
}
