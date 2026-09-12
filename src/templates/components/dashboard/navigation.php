<?php
/**
 * phpcs:ignoreFile WordPress.NamingConventions.PrefixAllGlobals -- Template files define local variables.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Component: Dashboard Tab Navigation
 */
?>
<!-- Modern Tabs -->
<div class="mb-4 border-bottom border-light">
    <nav class="nav-tabs-custom d-flex overflow-auto text-nowrap pb-1 no-scrollbar" aria-label="Tabs" style="-webkit-overflow-scrolling: touch;">
        <button id="btn-tab-home" data-tab-target="#tab-home" class="tab-btn active text-primary border-primary">My Home</button>
        <button id="btn-tab-community" data-tab-target="#tab-community" class="tab-btn">Community</button>
        
        <?php if ( ! class_exists( 'NAMMASOCIETY51_Module_Registry' ) || NAMMASOCIETY51_Module_Registry::is_enabled( 'helpdesk' ) ) : ?>
            <button id="btn-tab-requests" data-tab-target="#tab-requests" class="tab-btn">My Requests</button>
        <?php endif; ?>

        <?php if ( ! class_exists( 'NAMMASOCIETY51_Module_Registry' ) || NAMMASOCIETY51_Module_Registry::is_enabled( 'finance' ) ) : ?>
            <button id="btn-tab-accounts" data-tab-target="#tab-accounts" class="tab-btn">My Accounts</button>        
            <button id="btn-tab-expenses" data-tab-target="#tab-expenses" class="tab-btn">Society Finance</button>
        <?php endif; ?>

        <?php if ( ! class_exists( 'NAMMASOCIETY51_Module_Registry' ) || NAMMASOCIETY51_Module_Registry::is_enabled( 'facilities' ) ) : ?>
            <button id="btn-tab-facilities" data-tab-target="#tab-facilities" class="tab-btn">Facilities</button>
        <?php endif; ?>

        <?php if ( ! class_exists( 'NAMMASOCIETY51_Module_Registry' ) || NAMMASOCIETY51_Module_Registry::is_enabled( 'polls' ) ) : ?>
            <button id="btn-tab-polls" data-tab-target="#tab-polls" class="tab-btn">Polls</button>
        <?php endif; ?>

        <?php if ( ! class_exists( 'NAMMASOCIETY51_Module_Registry' ) || NAMMASOCIETY51_Module_Registry::is_enabled( 'rules' ) ) : ?>
            <button id="btn-tab-rules" data-tab-target="#tab-rules" class="tab-btn">Rules</button>
        <?php endif; ?>

        <?php if ( ! class_exists( 'NAMMASOCIETY51_Module_Registry' ) || NAMMASOCIETY51_Module_Registry::is_enabled( 'notices' ) ) : ?>
            <button id="btn-tab-notices" data-tab-target="#tab-notices" class="tab-btn">Notices</button>
        <?php endif; ?>

        <button id="btn-tab-notifications" data-tab-target="#tab-notifications" class="tab-btn d-flex align-items-center gap-1">
            Notifications
             <?php if(!empty($data['notifications'])): ?>
                <span class="badge bg-danger rounded-pill px-1 ms-1" style="font-size: 0.6rem;"><?php echo esc_html( count($data['notifications']) ); ?></span>
             <?php endif; ?>
        </button>
    </nav>
</div>
