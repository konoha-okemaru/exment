<?php

namespace Exceedone\Exment\Model\Traits;

use Exceedone\Exment\Enums\SystemTableName;
use Exceedone\Exment\Enums\JoinedOrgFilterType;
use Exceedone\Exment\Model\CustomColumn;
use Exceedone\Exment\Model\CustomRelation;
use Exceedone\Exment\Model\RoleGroup;
use Exceedone\Exment\Model\Define;
use Exceedone\Exment\Model\System;
use Encore\Admin\Traits\ModelTree;
use Encore\Admin\Traits\AdminBuilder;
use Exceedone\Exment\Services\AuthUserOrg\OrganizationTree;

trait OrganizationTrait
{
    use AdminBuilder, ModelTree {
        ModelTree::boot as treeBoot;
    }

    /**
     * get parent organization.
     * (*)Only one deeply parent. not all deeply parents.
     */
    public function parent_organization()
    {
        return $this->belongsTo(static::class, static::getParentOrgIndexName());
    }
 
    /**
     * get children organizations.
     * (*)Only one deeply organizations. not all deeply organizations.
     */
    public function children_organizations()
    {
        return $this->hasMany(static::class, static::getParentOrgIndexName());
    }

    public function hasChildren()
    {
        return count($this->children_organizations) > 0;
    }

    public function hasParent()
    {
        return isset($this->parent_organization);
    }

    public function users()
    {
        $db_table_name_pivot = CustomRelation::getRelationNameByTables(SystemTableName::ORGANIZATION, SystemTableName::USER);
        return $this->belongsToMany(getModelName(SystemTableName::USER), $db_table_name_pivot, "parent_id", "child_id");
    }

    public static function getParentOrgIndexName()
    {
        return CustomColumn::getEloquent('parent_organization', new static)->getIndexColumnName();
    }

    /**
     * Get avatar
     *
     * @return void
     */
    public function getDisplayAvatarAttribute()
    {
        // get default avatar
        return asset(Define::ORGANIZATION_IMAGE_LINK);
    }

    /**
     * get all deeply parent organizations.
     * (*) not contains this.
     *
     * @return array all_parent_organizations (as array)
     */
    public function all_parent_organizations()
    {
        $organizations = [];
        $deeps = intval(config('exment.organization_deeps', 4));
        $target = $this;
        for ($i = 0 ; $i < $deeps; $i++) {
            if (!isset($target->parent_organization)) {
                break;
            }
            $organizations[] = $target->parent_organization;
            $target = $target->parent_organization;
        }

        return $organizations;
    }

    /**
     * get all deeply children organizations.
     * (*) not contains this.
     *
     * @return array all_children_organizations (as array)
     */
    public function all_children_organizations()
    {
        $organizations = [];
        $deeps = intval(config('exment.organization_deeps', 4));
        static::setChildrenOrganizations(1, $this, $organizations);

        return $organizations;
    }

    /**
     * Get Parent and Children Ids for Authoritable
     *
     * @param string $filterType
     * @return array
     */
    public function getOrgAuthoritableIds($filterType = JoinedOrgFilterType::ALL) : array
    {
        return OrganizationTree\JoinHelper::getOrgAuthoritableIds($filterType, $this->getUserId());
    }

    /**
     * Get Parent and Children Ids for joined
     *
     * @param string $filterType
     * @return array
     */
    public function getOrgJoinedIds($filterType = JoinedOrgFilterType::ALL) : array
    {
        return OrganizationTree\TreeOrgHelper::getOrgJoinedIds($filterType, $this->id);
    }

    protected static function setChildrenOrganizations($deep, $target, &$organizations)
    {
        $deeps = intval(config('exment.organization_deeps', 4));

        if (!isset($target->children_organizations) || count($target->children_organizations) == 0) {
            return;
        }
        foreach ($target->children_organizations as $children_organization) {
            $organizations[] = $children_organization;
            if ($deep < $deeps) {
                static::setChildrenOrganizations(++$deep, $children_organization, $organizations);
            }
        }
    }
    
    /**
     * get role_group user or org joined.
     *
     * @return void
     */
    public function belong_role_groups()
    {
        return RoleGroup::whereHas('role_group_organizations', function ($query) {
            $query->where('role_group_target_id', $this->id);
        })->get();
    }
}
