<?php

namespace Exceedone\Exment\ColumnItems\FormOthers;

use Exceedone\Exment\ColumnItems\FormOtherItem;
use Exceedone\Exment\Form\Field;

class Description extends FormOtherItem
{
    protected function getAdminFieldClass()
    {
        return Field\Description::class;
    }
}
