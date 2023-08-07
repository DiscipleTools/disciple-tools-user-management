<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'disciple-tools-user-management/disciple-tools-user-management.php' );

        $this->assertContains(
            'disciple-tools-user-management/disciple-tools-user-management.php',
            get_option( 'active_plugins' )
        );
    }
}
