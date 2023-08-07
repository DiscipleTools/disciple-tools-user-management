<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'dt-user-management/dt-user-management.php' );

        $this->assertContains(
            'dt-user-management/dt-user-management.php',
            get_option( 'active_plugins' )
        );
    }
}
