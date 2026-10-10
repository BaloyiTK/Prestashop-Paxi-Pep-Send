<?php
if (!defined('_PS_VERSION_')) { exit; }

function upgrade_module_1_3_1($module)
{
    if (!$module->isRegisteredInHook('actionValidateStepComplete')) {
        return $module->registerHook('actionValidateStepComplete');
    }
    return true;
}
