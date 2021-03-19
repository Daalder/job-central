<?php

namespace Daalder\JobCentral\Validators;

use Illuminate\Validation\Validator;

class JobCentralValidator
{
    public function validateEmptyWith($attribute, $value, array $parameters)
    {
        return ($value != '' && request()->query->has($parameters[0])) ? false : true;
    }
}