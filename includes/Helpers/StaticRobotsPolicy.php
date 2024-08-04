<?php

namespace MediaWiki\Extension\TranslateTweaks\Helpers;

use MediaWiki\Title\Title;

/**
 * Modifies and interacts with the global $wgArticleRobotPolicies config, which doesn't have a usual Config setter
 */
class StaticRobotsPolicy {
    /**
     * @param Title $title
     * @return mixed
     */
    public static function get( Title $title ): mixed {
        global $wgArticleRobotPolicies; // Could access config but a setter isn't available
        return $wgArticleRobotPolicies[$title -> getPrefixedText()] ?? null;
    }

    /**
     * @param Title $title
     * @param string|null $policy
     * @return void
     */
    public static function set( Title $title, ?string $policy ): void {
        global $wgArticleRobotPolicies; // Could access config but a setter isn't available
        if ( $policy ) {
            $wgArticleRobotPolicies[$title -> getPrefixedText()] = $policy;
        } else {
            unset($wgArticleRobotPolicies[$title -> getPrefixedText()]);
        }
    }
}