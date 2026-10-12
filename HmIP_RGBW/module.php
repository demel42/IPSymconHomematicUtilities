<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HmIPRGBW extends IPSModule
{
    use HomematicUtilities\StubsCommonLib;
    use HomematicUtilitiesLocalLib;

    private $SemaphoreID;
    private $SemaphoreTM;

    private static $ModuleActivityLines = 200;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
        $this->SemaphoreTM = 30 * 1000;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('chan0_instID', 0);
        $this->RegisterPropertyInteger('chan1_instID', 0);

        $this->RegisterAttributeString('wake_up_light', json_encode([]));

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('TimerWakeUpLight', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "LoopWakeUpLight", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            // check pending transmission
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $instID = $this->ReadPropertyInteger('chan0_instID');
        if (IPS_InstanceExists($instID) == false) {
            $this->SendDebug(__FUNCTION__, '"chan0_instID" must be defined', 0);
            $field = $this->Translate('Instance of Channel 0');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }
        $instID = $this->ReadPropertyInteger('chan1_instID');
        if (IPS_InstanceExists($instID) == false) {
            $this->SendDebug(__FUNCTION__, '"chan1_instID" must be defined', 0);
            $field = $this->Translate('Instance of Channel 1');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = [
            'chan0_instID',
            'chan1_instID',
        ];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 1;

        $this->MaintainVariable('LastSync', $this->Translate('Last sync'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            // check pending transmission
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('HmIP RGBW');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'         => 'RowLayout',
            'items'        => [
                [
                    'type'         => 'Label',
                    'caption'      => 'Homematic instances',
                ],
                [
                    'type'         => 'SelectInstance',
                    'validModules' => ['{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}'],
                    'name'         => 'chan1_instID',
                    'caption'      => 'Channel 1'
                ],
                [
                    'type'         => 'SelectInstance',
                    'validModules' => ['{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}'],
                    'name'         => 'chan0_instID',
                    'caption'      => 'Channel 0'
                ],
            ],
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();
        $formActions[] = $this->GetModuleActivityFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'LoopWakeUpLight':
                $this->LoopWakeUpLight();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function TimerWakeUpLight()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $last_sync = $this->GetValue('LastSync');
        $now = time();

        $sec = 0;

        $this->MaintainTimer('TimerWakeUpLight', $sec * 1000);
    }

    public function SendCombinedParameter(int $level, int $hue, int $saturation)
    {
        if ($level < 0 || $level > 100) {
            $this->SendDebug(__FUNCTION__, 'level $level ist not between 0..100 %', 0);
            return false;
        }
        if ($hue < 0 || $hue > 360) {
            $this->SendDebug(__FUNCTION__, 'hue $hue ist not between 0..360 °', 0);
            return false;
        }
        if ($saturation < 0 || $saturation > 100) {
            $this->SendDebug(__FUNCTION__, 'saturation $saturation ist not between 0..100 %', 0);
            return false;
        }

        $instID = $this->ReadPropertyInteger('chan1_instID');
        $ident = 'COMBINED_PARAMETER';

        $paramV = [];
        $paramV[] = 'L=' . $level;
        $paramV[] = 'H=' . $hue;
        $paramV[] = 'SAT=' . $saturation;

        $paramV[] = 'OT=0'; // Einschaltzeit
        $paramV[] = 'RT=0'; // Zampenzeit (Sec)
        $paramV[] = 'RTTOV=0'; // Rampen-Ausschaltzeit
        $paramV[] = 'RTTOU=0'; // Einheit von RTTOV (0=sec, 1=min, 3=msec)

        $paramS = implode(',', $paramV);

        $r = @HM_WriteValueString($instID, $ident, $paramS);
        if ($r == false) {
            $msg = 'HM_WriteValueString(' . $instID . ', \'' . $ident . '\', ' . $paramS . ') failed';
            $this->SendDebug(__FUNCTION__, $msg, 0);
            return false;
        } else {
            $msg = 'HM_WriteValueString(' . $instID . ', \'' . $ident . '\', ' . $paramS . ') succed';
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $this->SetValue('LastSync', time());
            return true;
        }
    }

    // HSV in RGB umrechnen
    public function HSV2RGB(float $h, float $s, float $v): array
    {
        $s /= 100;
        $v /= 100;

        if ($s == 0) {
            $r = $g = $b = $v * 255;
        } else {
            $h /= 60;
            $i = floor($h);
            $f = $h - $i;

            $p = $v * (1 - $s);
            $q = $v * (1 - $s * $f);
            $t = $v * (1 - $s * (1 - $f));

            switch ($i) {
                case 0:
                    $r = $v;
                    $g = $t;
                    $b = $p;
                    break;
                case 1:
                    $r = $q;
                    $g = $v;
                    $b = $p;
                    break;
                case 2:
                    $r = $p;
                    $g = $v;
                    $b = $t;
                    break;
                case 3:
                    $r = $p;
                    $g = $q;
                    $b = $v;
                    break;
                case 4:
                    $r = $t;
                    $g = $p;
                    $b = $v;
                    break;
                default:
                    $r = $v;
                    $g = $p;
                    $b = $q;
                    break;
            }

            $r *= 255;
            $g *= 255;
            $b *= 255;
        }

        return [
            'R' => $r,
            'G' => $g,
            'B' => $b,
        ];
    }

    // HSV in RGBW umrechnen
    public function HSV2RGBW(float $h, float $s, float $v): array
    {
        $rgb = $this->HSV2RGB($h, $s, $v);

        // Der gemeinsame Nenner aller Farbkanäle repräsentiert den Weiß-Anteil.
        $w = min($rgb['R'], $rgb['G'], $rgb['B']);

        // Weiß-Anteil von den RGB-Kanälen abziehen, um Mischlicht-Verfälschungen zu verhindern
        $r = $rgb['R'] - $w;
        $g = $rgb['G'] - $w;
        $b = $rgb['B'] - $w;

        return [
            'R' => $r,
            'G' => $g,
            'B' => $b,
            'W' => $w,
        ];
    }

    // Berechnung eines Lichtwecker-Schrittes im HSV-Farbraum und RGBW-Werten
    // progress 0..1
    public function calcLightWakeupStep(float $progress): array
    {
        // Grenzen absichern
        $progress = max(0.0, min(1.0, $progress));

        // HSV-KURVEN DESIGNEN
        // Hue (H): Wandert von 0° (Tiefrot) zu ca. 50° (Warmgelb)
        $h = 0 + ($progress * 50);

        // Value/Brightness (V): Steigt quadratisch für ein sanfteres Aufwachen im dunklen Bereich
        $v = pow($progress, 2) * 100;

        // Saturation (S): Bleibt lange intensiv, fällt erst in der zweiten Hälfte stark ab für echtes Weiß
        if ($progress < 0.5) {
            $s = 100;
        } else {
            // Linearer Abfall von 100 auf 0 in der zweiten Hälfte
            $s = 100 - (($progress - 0.5) * 2 * 100);
        }

        return [
            'H' => round($h, 1),
            'S' => round($s, 1),
            'V' => round($v, 1),
        ];
    }

    public function calculateSunrise(float $progress, float $maxBrightness = 1.0): array
    {
        // 1. Eingrenzung des Fortschritts zur Sicherheit
        $progress = max(0.0, min(1.0, $progress));
        $maxBrightness = max(0.0, min(1.0, round($maxBrightness / 100)));

        // 2. Nicht-lineare Kurve für die Helligkeit (Value) verwenden (Quadratischer Anstieg)
        // Das sorgt dafür, dass es am Anfang sehr sanft hell wird.
        // maxBrightness begrenzt die Helligkeit
        if ($progress == 0.0 || $maxBrightness == 0.0) {
            $v = 0.0;
        } else {
            $minBrightness = 0.04; // Startet sofort bei ca. 4% Helligkeit

            // Falls das gewünschte Maximum unter dem Minimum liegt, passen wir das Minimum an
            if ($minBrightness >= $maxBrightness) {
                $minBrightness = $maxBrightness * 0.2;
            }

            // Mathematische Mischkurve für den sanften Verlauf (0.0 bis 1.0)
            $kurve = (0.4 * $progress) + (0.6 * pow($progress, 2));

            // Skalierung exakt ZWISCHEN dem Minimum und dem dynamischen Maximum
            $v = $minBrightness + ($kurve * ($maxBrightness - $minBrightness));
        }

        // 3. Berechnung des Farbtons (Hue: 0 - 360)
        // Pfad: Nachtblau (240°) -> Rot (360°/0°) -> Orange (30°) -> Gelb/Warmweiß (55°)
        if ($progress < 0.4) {
            // Erste Phase (Dämmerung): Von Blau (240°) fließend zu reinem Rot (360°)
            $norm = $progress / 0.4;
            $h = 240 + ($norm * 120);
        } else {
            // Zweite Phase (Aufgang): Von Rot (0°) zu Sonnengelb/Warmweiß (55°)
            $norm = ($progress - 0.4) / 0.6;
            $h = 0 + ($norm * 55);
        }

        // 4. Berechnung der Sättigung (Saturation: 0.0 - 1.0)
        // Bleibt am Anfang hoch für kräftige Dämmerungsfarben und fällt am Ende ab
        if ($progress < 0.6) {
            $s = 1.0;
        } else {
            // Entsättigung am Ende für helles, weißeres Licht
            $norm = ($progress - 0.6) / 0.4;
            $s = 1.0 - ($norm * 0.65); // Endet bei ca. 0.35 Sättigung
        }

        return [
            'H' => (int) round($h),
            'S' => (int) round($s * 100),
            'V' => (int) round($v * 100),
        ];
    }

    public function StartWakeUpLight(int $minutes, int $target_brightness)
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->SemaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $paramV = @json_decode((string) $this->ReadAttributeString('wake_up_light'), true);
        $step = (int) $this->GetArrayElem($paramV, 'step', 0);
        if ($step > 0) {
            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'almost active WakeUp  step=' . $step . ', parameters=' . print_r($paramV, true), 0);
            return false;
        }

        $step = 0;
        $start_ts = time();
        $duration = $minutes * 60;
        $step_duration = 30;
        $n_steps = round($duration / $step_duration);

        $paramV = [
            'step'              => $step,
            'start_ts'          => $start_ts,
            'duration'          => $duration,
            'step_duration'     => $step_duration,
            'n_steps'           => $n_steps,
            'target_brightness' => $target_brightness,
        ];

        $this->WriteAttributeString('wake_up_light', json_encode($paramV));

        $secs = $step * $step_duration;
        $progress = $secs > 0 ? $secs / $duration : 0;

        $hsv = $this->calculateSunrise($progress, $target_brightness);
        $r = $this->SendCombinedParameter($hsv['V'], $hsv['H'], $hsv['S']);
        $this->SendDebug(__FUNCTION__, 'step=' . $step . ', progress=' . ($progress * 100) . '%, hsv=' . print_r($hsv, true) . ', SendCombinedParameter() ' . ($r ? 'succeeded' : 'failed'), 0);
        if ($r) {
            $paramV['hsv'] = $hsv;
        }

        $step++;
        $paramV['step'] = $step;
        $this->WriteAttributeString('wake_up_light', json_encode($paramV));

        $delay = $start_ts + ($step * $step_duration) - time();
        if ($delay <= 0) {
            $delay = $step_duration;
        }
        $this->MaintainTimer('TimerWakeUpLight', $delay * 1000);

        $msg = 'start WakeUpLight with H=' . $hsv['H'] . ', S=' . $hsv['S'] . ', L=' . $hsv['V'];
        if ($r == false) {
            $msg .= ', transmission failed';
        }
        $this->AddModuleActivity($msg, self::$ModuleActivityLines);

        IPS_SemaphoreLeave($this->SemaphoreID);
        return true;
    }

    public function LoopWakeUpLight()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->SemaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $paramV = @json_decode((string) $this->ReadAttributeString('wake_up_light'), true);
        $step = (int) $this->GetArrayElem($paramV, 'step', 0);
        if ($step == 0) {
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainTimer('TimerWakeUpLight', 0);

            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'no active WakeUp', 0);
            return false;
        }

        $n_steps = (int) $this->GetArrayElem($paramV, 'n_steps', 0);
        if ($step > $n_steps) {
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainTimer('TimerWakeUpLight', 0);

            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'already completed WakeUp', 0);
            return false;
        }

        $start_ts = (int) $this->GetArrayElem($paramV, 'start_ts', 0);
        $step_duration = (int) $this->GetArrayElem($paramV, 'step_duration', 0);
        $duration = (int) $this->GetArrayElem($paramV, 'duration', 0);
        $target_brightness = (int) $this->GetArrayElem($paramV, 'target_brightness', 0);

        $secs = $step * $step_duration;
        $progress = $secs > 0 ? $secs / $duration : 0;

        $hsv = $this->calculateSunrise($progress, $target_brightness);
        $r = $this->SendCombinedParameter($hsv['V'], $hsv['H'], $hsv['S']);
        $this->SendDebug(__FUNCTION__, 'step=' . $step . ', progress=' . ($progress * 100) . '%, hsv=' . print_r($hsv, true) . ', SendCombinedParameter() ' . ($r ? 'succeeded' : 'failed'), 0);
        if ($r) {
            $paramV['hsv'] = $hsv;
        }

        $step++;
        $paramV['step'] = $step;
        if ($step > $n_steps) {
            $this->SendDebug(__FUNCTION__, 'completed WakeUp', 0);
            $this->WriteAttributeString('wake_up_light', json_encode([]));
            $this->MaintainTimer('TimerWakeUpLight', 0);
            $msg = 'finished WakeUpLight step=' . ($step - 1) . ', progress=' . ($progress * 100) . '% with H=' . $hsv['H'] . ', S=' . $hsv['S'] . ', L=' . $hsv['V'];
        } else {
            $this->SendDebug(__FUNCTION__, 'continue WakeUp', 0);
            $this->WriteAttributeString('wake_up_light', json_encode($paramV));
            $delay = $start_ts + ($step * $step_duration) - time();
            if ($delay <= 0) {
                $delay = $step_duration;
            }
            $this->MaintainTimer('TimerWakeUpLight', $delay * 1000);
            $msg = 'executed WakeUpLight step=' . ($step - 1) . ', progress=' . ($progress * 100) . '% with H=' . $hsv['H'] . ', S=' . $hsv['S'] . ', L=' . $hsv['V'];
        }

        IPS_SemaphoreLeave($this->SemaphoreID);

        $this->AddModuleActivity($msg, self::$ModuleActivityLines);
        return true;
    }

    public function StopWakeUpLight()
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->SemaphoreTM) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $paramV = @json_decode((string) $this->ReadAttributeString('wake_up_light'), true);
        $step = (int) $this->GetArrayElem($paramV, 'step', 0);
        if ($step == 0) {
            IPS_SemaphoreLeave($this->SemaphoreID);

            $this->SendDebug(__FUNCTION__, 'no active WakeUp', 0);
            return false;
        }

        $this->WriteAttributeString('wake_up_light', json_encode([]));
        $this->MaintainTimer('TimerWakeUpLight', 0);

        IPS_SemaphoreLeave($this->SemaphoreID);

        $this->SendDebug(__FUNCTION__, 'stopped WakeUp', 0);
        $msg = 'stopped WakeUpLight';

        $this->AddModuleActivity($msg, self::$ModuleActivityLines);
        return true;
    }
}
