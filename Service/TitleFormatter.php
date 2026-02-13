<?php
/**
 * Copyright (c) Lobbster
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Lobbster\ProductViewAttributeGroups\Service;

/**
 * Formats attribute group name into display title (strip prefix, prettify).
 */
class TitleFormatter
{
    /**
     * Strip prefix from group name, then prettify (underscores/hyphens to spaces, trim, title case).
     *
     * If stripped part is empty, return original name.
     *
     * @param string $groupName Raw attribute group name (e.g. "pview_Truck")
     * @param string $prefix Prefix to strip (e.g. "pview_")
     * @return string Display title (e.g. "Truck")
     */
    public function format(string $groupName, string $prefix): string
    {
        $groupNameLower = mb_strtolower($groupName, 'UTF-8');
        $stripped = $groupName;
        if ($prefix !== '') {
            $prefixLower = mb_strtolower($prefix, 'UTF-8');
            $prefixAlt = str_replace('_', '-', $prefixLower);
            if (str_starts_with($groupNameLower, $prefixLower)) {
                $stripped = mb_substr($groupName, mb_strlen($prefixLower, 'UTF-8'), null, 'UTF-8');
            } elseif (str_starts_with($groupNameLower, $prefixAlt)) {
                $stripped = mb_substr($groupName, mb_strlen($prefixAlt, 'UTF-8'), null, 'UTF-8');
            }
        }

        $stripped = trim($stripped);
        if ($stripped === '') {
            $stripped = $groupName;
        }

        $prettified = preg_replace('/[\s_\-]+/u', ' ', $stripped);
        $prettified = trim($prettified ?? $stripped);
        $result = $prettified !== '' ? $this->titleCase($prettified) : $stripped;
        return $result !== '' ? $result : $groupName;
    }

    /**
     * Convert string to title case (UTF-8).
     *
     * @param string $str
     * @return string
     */
    private function titleCase(string $str): string
    {
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }
}
