<?php

test('guests are redirected from home to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});
