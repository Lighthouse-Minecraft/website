<?php

use App\Models\Meeting;

describe('Meeting Model', function () {
    it('exists as a valid model class', function () {
        expect(new Meeting)->toBeInstanceOf(Meeting::class);
    });

    todo('add model logic tests here when needed');
});
