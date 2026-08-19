<?php

use Tests\Concerns\RefreshesDatabaseAsOwner;
use Tests\TestCase;

uses(TestCase::class, RefreshesDatabaseAsOwner::class)->in('Feature');
uses(TestCase::class, RefreshesDatabaseAsOwner::class)->in('Browser');
