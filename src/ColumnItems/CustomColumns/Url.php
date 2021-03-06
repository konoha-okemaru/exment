<?php

namespace Exceedone\Exment\ColumnItems\CustomColumns;

use Exceedone\Exment\ColumnItems\CustomItem;
use Exceedone\Exment\Enums\UrlTagType;
use Encore\Admin\Form\Field;

class Url extends CustomItem
{
    /**
     * get html(for display)
     * *this function calls from non-escaping value method. So please escape if not necessary unescape.
     */
    protected function _html($v)
    {
        $value = $this->_value($v);
        $url = $this->_value($v);

        $value = boolval(array_get($this->options, 'grid_column')) ? get_omitted_string($value) : $value;
        
        return \Exment::getUrlTag($url, $value, UrlTagType::BLANK);
    }
    
    protected function getAdminFieldClass()
    {
        return Field\Url::class;
    }
}
