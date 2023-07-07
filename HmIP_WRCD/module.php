<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class HmIPWRCD extends IPSModule
{
    use HomematicUtilities\StubsCommonLib;
    use HomematicUtilitiesLocalLib;

    private static $semaphoreTM = 5 * 1000;

    private static $numberOfLines = 5;
    private static $maxCharOnly = 14;
    private static $maxCharWithIcon = 11;

    private static $STEP_NEW = 0;
    private static $STEP_READY = 1;
    private static $STEP_FORCE = 2;
    private static $STEP_DONE = 3;
    private static $STEP_FAIL = 4;

    private $ModuleDir;
    private $SemaphoreID;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('chan0_instID', 0);
        $this->RegisterPropertyInteger('chan3_instID', 0);

        $this->RegisterPropertyInteger('delay_before', 5);
        $this->RegisterPropertyInteger('pause_between', 5);

        $this->RegisterAttributeString('parameter', '');
        $this->RegisterAttributeInteger('step', self::$STEP_NEW);
        $this->RegisterAttributeString('last_parameter', '');

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('TimerLoop', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "TimerLoop", "");');

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
        $instID = $this->ReadPropertyInteger('chan3_instID');
        if (IPS_InstanceExists($instID) == false) {
            $this->SendDebug(__FUNCTION__, '"chan3_instID" must be defined', 0);
            $field = $this->Translate('Instance of Channel 3');
            $r[] = $this->TranslateFormat('Field "{$field}" is not configured', ['{$field}' => $field]);
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $propertyNames = [
            'chan0_instID',
            'chan3_instID',
        ];
        $this->MaintainReferences($propertyNames);

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('TimerLoop', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('TimerLoop', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('TimerLoop', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 1;

        $this->MaintainVariable('LastSync', $this->Translate('Last sync'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('TimerLoop', 0);
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
        $formElements = $this->GetCommonFormElements('HmIP WRCD');

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
                    'name'         => 'chan3_instID',
                    'caption'      => 'Channel 3'
                ],
                [
                    'type'         => 'SelectInstance',
                    'validModules' => ['{EE4A81C6-5C90-4DB7-AD2F-F6BBD521412E}'],
                    'name'         => 'chan0_instID',
                    'caption'      => 'Channel 0'
                ],
            ],
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'delay_before',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Delay before transmission',
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'pause_between',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Pause between transmissions',
        ];

        $formElements[] = [
            'type'    => 'Label',
        ];

        $lines_values = [];
        $signal_values = [];
        $last_parameter = @json_decode($this->ReadAttributeString('last_parameter'), true);
        if (isset($last_parameter['lines'])) {
            for ($row = 1; $row <= self::$numberOfLines; $row++) {
                if (isset($last_parameter['lines'][$row])) {
                    $line = $last_parameter['lines'][$row];
                    $lines_values[] = [
                        'row'            => $line['row'],
                        'text'           => $line['text'],
                        'textcolor'      => $this->ColorFormatted($line['textcolor'], true),
                        'background'     => $this->ColorFormatted($line['background'], true),
                        'alignment'      => $this->AlignmentFormatted($line['alignment'], true),
                        'icon'           => $this->IconFormatted($line['icon'], true),
                    ];
                }
            }
        }
        if (isset($last_parameter['signal'])) {
            $signal = $last_parameter['signal'];
            $signal_values[] = [
                'sound'      => $this->SoundFormatted($signal['sound'], true),
                'repetition' => $this->RepetitionFormatted($signal['repetition'], true),
                'interval'   => $this->IntervalFormatted($signal['interval'], true),
            ];
        }

        $formElements[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Last transmission',
            'expanded'  => true,
            'items'     => [
                [
                    'type'     => 'List',
                    'columns'  => [
                        [
                            'name'     => 'row',
                            'width'    => '50px',
                            'caption'  => 'Row',
                        ],
                        [
                            'name'     => 'text',
                            'width'    => 'auto',
                            'caption'  => 'Text',
                        ],
                        [
                            'name'     => 'textcolor',
                            'width'    => '150px',
                            'caption'  => 'Textcolor',
                        ],
                        [
                            'name'     => 'background',
                            'width'    => '150px',
                            'caption'  => 'Background',
                        ],
                        [
                            'name'     => 'alignment',
                            'width'    => '200px',
                            'caption'  => 'Alignment',
                        ],
                        [
                            'name'     => 'icon',
                            'width'    => '250px',
                            'caption'  => 'Icon',
                        ],
                    ],
                    'add'      => false,
                    'delete'   => false,
                    'rowCount' => 5,
                    'values'   => $lines_values,
                    'caption'  => 'Textlines',
                ],
                [
                    'type'     => 'List',
                    'columns'  => [
                        [
                            'name'     => 'sound',
                            'width'    => '200px',
                            'caption'  => 'Sound',
                        ],
                        [
                            'name'     => 'repetition',
                            'width'    => '150px',
                            'caption'  => 'Repetition',
                        ],
                        [
                            'name'     => 'interval',
                            'width'    => '150px',
                            'caption'  => 'Interval',
                        ],
                    ],
                    'add'      => false,
                    'delete'   => false,
                    'rowCount' => 1,
                    'values'   => $signal_values,
                    'caption'  => 'Signal',
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
            'type'    => 'Button',
            'caption' => 'Retransmit',
            'onClick' => 'IPS_RequestAction($id, "Retransmit", "");',
        ];

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

        return $formActions;
    }

    public function SetLine(int $row, string $text, int $textcolor, int $background, int $alignment, int $icon)
    {
        if ($row <= 0 || $row > self::$numberOfLines) {
            $this->SendDebug(__FUNCTION__, 'invalid row ' . $row . ', must be between 1 and 5', 0);
            return false;
        }

        if ($this->ColorFormatted($textcolor, false) == false) {
            $this->SendDebug(__FUNCTION__, 'invalid textcolor ' . $textcolor, 0);
            return false;
        }
        if ($this->ColorFormatted($background, false) == false) {
            $this->SendDebug(__FUNCTION__, 'invalid background ' . $background, 0);
            return false;
        }
        if ($this->AlignmentFormatted($alignment, false) == false) {
            $this->SendDebug(__FUNCTION__, 'invalid alignment ' . $alignment, 0);
            return false;
        }
        if ($icon != 0 && $this->IconFormatted($icon, false) == false) {
            $this->SendDebug(__FUNCTION__, 'invalid icon ' . $icon, 0);
            return false;
        }

        if ($text == '') {
            $textcolor = 0;
            $background = 0;
            $alignment = 0;
        }

        $j = [
            'row=' . $row,
            'text="' . $text . '"',
            'textcolor=' . $textcolor . '(' . $this->ColorFormatted($textcolor, true) . ')',
            'background=' . $background . '(' . $this->ColorFormatted($background, true) . ')',
            'alignment=' . $alignment . '(' . $this->AlignmentFormatted($alignment, true) . ')',
            'icon=' . $icon . '(' . $this->IconFormatted($icon, true) . ')',
        ];
        $msg = implode(', ', $j);
        $this->SendDebug(__FUNCTION__, $msg, 0);

        $parameter = @json_decode($this->ReadAttributeString('parameter'), true);
        $lines = isset($parameter['lines']) ? $parameter['lines'] : [];
        $lines[$row] = [
            'row'        => $row,
            'text'       => $text,
            'textcolor'  => $textcolor,
            'background' => $background,
            'alignment'  => $alignment,
            'icon'       => $icon,
        ];
        $parameter['lines'] = $lines;
        $this->WriteAttributeString('parameter', json_encode($parameter));

        $this->WriteAttributeInteger('step', self::$STEP_NEW);

        return true;
    }

    public function SetSignal(int $sound, int $repetition, int $interval)
    {
        if ($sound != -1 && $this->SoundFormatted($sound, false) == false) {
            $this->SendDebug(__FUNCTION__, 'invalid sound ' . $sound, 0);
            return false;
        }
        if ($repetition < 0 || $repetition > 15) {
            $this->SendDebug(__FUNCTION__, 'invalid repetition ' . $repetition . ', mus be between 0 (no), 1 and 14 or 15 (infinite)', 0);
            return false;
        }
        if ($interval < 1 || $interval > 15) {
            $this->SendDebug(__FUNCTION__, 'invalid interval ' . $interval . ', must be between 1 and 15', 0);
            return false;
        }

        $j = [
            'sound=' . $sound . '(' . $this->SoundFormatted($sound, true) . ')',
            'repetition=' . $repetition,
            'interval=' . $interval,
        ];
        $msg = implode(', ', $j);
        $this->SendDebug(__FUNCTION__, $msg, 0);

        $parameter = @json_decode($this->ReadAttributeString('parameter'), true);
        $parameter['signal'] = [
            'sound'      => $sound,
            'repetition' => $repetition,
            'interval'   => $interval,
        ];
        $this->WriteAttributeString('parameter', json_encode($parameter));

        $this->WriteAttributeInteger('step', self::$STEP_NEW);

        return true;
    }

    public function Deliver(bool $delayed, bool $force)
    {
        $this->SendDebug(__FUNCTION__, 'delayed=' . $this->bool2str($delayed) . ', force=' . $this->bool2str($force), 0);

        $this->WriteAttributeInteger('step', $force ? self::$STEP_FORCE : self::$STEP_READY);

        $delay_before = $this->ReadPropertyInteger('delay_before');
        $pause_between = $this->ReadPropertyInteger('pause_between');

        $last_sync = $this->GetValue('LastSync');
        $now = time();

        $sec = 0;
        if ($delayed) {
            if (($now - $last_sync) < $pause_between) {
                $sec = ($last_sync + $pause_between) - $now;
            }
            if ($sec < $delay_before) {
                $sec = $delay_before;
            }
        }

        $this->MaintainTimer('TimerLoop', $sec * 1000);
        if ($sec == 0) {
            $this->Transmit();
        }
    }

    private function TimerLoop()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $pause_between = $this->ReadPropertyInteger('pause_between');

        $last_sync = $this->GetValue('LastSync');
        $now = time();

        if (($now - $last_sync) < $pause_between) {
            $sec = ($last_sync + $pause_between) - $now;
        } else {
            $sec = 0;
        }

        $this->MaintainTimer('TimerLoop', $sec * 1000);
        if ($sec == 0) {
            $this->Transmit();
        }
    }

    private function Transmit()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $step = $this->ReadAttributeInteger('step');

        if (in_array($step, [self::$STEP_READY, self::$STEP_FORCE]) == false) {
            $this->SendDebug(__FUNCTION__, 'transmission not delivered', 0);
            return;
        }

        $src_chars = ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß', '°'];
        $dst_chars = ['{', '|', '}', '[', '#', '$', '_', '^'];

        $parameter = @json_decode($this->ReadAttributeString('parameter'), true);
        $last_parameter = @json_decode($this->ReadAttributeString('last_parameter'), true);

        $chg_lines = [];
        if (isset($parameter['lines'])) {
            for ($row = 1; $row <= self::$numberOfLines; $row++) {
                $line = isset($parameter['lines'][$row]) ? $parameter['lines'][$row] : [];
                $last_line = isset($last_parameter['lines'][$row]) ? $last_parameter['lines'][$row] : [];
                if ($step == self::$STEP_FORCE || json_encode($last_line) != json_encode($line)) {
                    $chg_lines[] = $line;
                }
            }
        }

        $paramV = [];
        $n = 0;
        foreach ($chg_lines as $line) {
            $row = $line['row'];
            $text = $line['text'];
            $textcolor = $line['textcolor'];
            $background = $line['background'];
            $alignment = $line['alignment'];
            $icon = $line['icon'];

            $text = str_replace($src_chars, $dst_chars, $text);

            $v = [
                'DDBC=' . $this->ColorFormatted($background, false),
                'DDTC=' . $this->ColorFormatted($textcolor, false),
                'DDI=' . $icon,
                'DDA=' . $this->AlignmentFormatted($alignment, false),
                'DDS=' . $text,
                'DDID=' . $row,
            ];
            $n++;
            if ($n == count($chg_lines)) {
                $v[] = 'DDC=true';
            }
            $paramV[] .= '{' . implode(',', $v) . '}';
        }

        if (isset($parameter['signal'])) {
            $signal = $parameter['signal'];
            $sound = $signal['sound'];
            $repetition = $signal['repetition'];
            $interval = $signal['interval'];

            if ($sound != -1) {
                $v = [
                    'R=' . $repetition,
                    'IN=' . $interval,
                    'ANS=' . $sound,
                ];
                $paramV[] .= '{' . implode(',', $v) . '}';
            }
        }
        $paramS = implode(',', $paramV);
        $this->SendDebug(__FUNCTION__, 'paramV=' . print_r($paramV, true), 0);

        $instID = $this->ReadPropertyInteger('chan3_instID');
        $ident = 'COMBINED_PARAMETER';

        $last_parameter = $this->ReadAttributeString('last_parameter');
        if (count($paramV) == 0) {
            $this->SendDebug(__FUNCTION__, $ident . ' is unchanged - no transmission', 0);
            return true;
        }

        $r = @HM_WriteValueString($instID, $ident, $paramS);
        if ($r == false) {
            $msg = 'HM_WriteValueString(' . $instID . ', \'' . $ident . '\', ' . $paramS . ') failed';
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $msg, KL_NOTIFY);
            $this->WriteAttributeInteger('step', self::$STEP_FAIL);
            $this->WriteAttributeString('last_parameter', '');
        } else {
            $msg = 'HM_WriteValueString(' . $instID . ', \'' . $ident . '\', ' . $paramS . ') succed';
            $this->SendDebug(__FUNCTION__, $msg, 0);
            $this->WriteAttributeInteger('step', self::$STEP_DONE);
            $this->WriteAttributeString('last_parameter', json_encode($parameter));
            $new_parameter = [
                'lines'  => $parameter['lines'],
                'signal' => [
                    'sound'      => -1,
                    'repetition' => 0,
                    'interval'   => 1,
                ],
            ];
            $this->WriteAttributeString('parameter', json_encode($new_parameter));
            $this->SetValue('LastSync', time());
        }
        return $r;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'TimerLoop':
                $this->TimerLoop();
                break;
            case 'Transmit':
                $this->Transmit();
                break;
            case 'Retransmit':
                $this->Deliver(false, true);
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

    private function ColorMapping()
    {
        $opts = [
            0 => ['tag' => 'WHITE', 'caption' => 'White'],
            1 => ['tag' => 'BLACK', 'caption' => 'Black'],
        ];

        return $opts;
    }

    private function ColorFormatted($val, $useCaption)
    {
        $maps = $this->ColorMapping();
        if (isset($maps[$val]) == false) {
            return false;
        }
        $map = $maps[$val];
        return $useCaption ? $this->Translate($map['caption']) : $map['tag'];
    }

    private function ColorDecode(string $ident)
    {
        $color = 0;
        $maps = $this->ColorMapping();
        foreach ($maps as $index => $map) {
            if ($map['tag'] == strtolower($ident)) {
                $color = $index;
                break;
            }
        }
        return $color;
    }

    public function ColorAsOptions()
    {
        $maps = $this->ColorMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $this->Translate($e['caption']),
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function AlignmentMapping()
    {
        $opts = [
            0 => ['tag' => 'LEFT', 'caption' => 'Left'],
            1 => ['tag' => 'CENTER', 'caption' => 'Center'],
            2 => ['tag' => 'RIGHT', 'caption' => 'Right'],
        ];

        return $opts;
    }

    private function AlignmentFormatted($val, $useCaption)
    {
        $maps = $this->AlignmentMapping();
        if (isset($maps[$val]) == false) {
            return false;
        }
        $map = $maps[$val];
        return $useCaption ? $this->Translate($map['caption']) : $map['tag'];
    }

    private function AlignmentDecode(string $ident)
    {
        $color = 0;
        $maps = $this->AlignmentMapping();
        foreach ($maps as $index => $map) {
            if ($map['tag'] == strtolower($ident)) {
                $color = $index;
                break;
            }
        }
        return $color;
    }

    public function AlignmentAsOptions()
    {
        $maps = $this->AlignmentMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $this->Translate($e['caption']),
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function IconMapping()
    {
        $opts = [
            0  => ['tag' => 'NONE', 'caption' => 'None'],
            1  => ['tag' => 'LAMP_OFF', 'caption' => 'Lamp off'],
            2  => ['tag' => 'LAMP_ON', 'caption' => 'Lamp on'],
            3  => ['tag' => 'PADLOCK_OPEN', 'caption' => 'Padlock open'],
            4  => ['tag' => 'PADLOCK_CLOSED', 'caption' => 'Padlock closed'],
            5  => ['tag' => 'ERROR', 'caption' => 'Error'],
            6  => ['tag' => 'OKAY', 'caption' => 'Okay'],
            7  => ['tag' => 'INFORMATION', 'caption' => 'Info'],
            8  => ['tag' => 'NEW_MESSAGE', 'caption' => 'New message'],
            9  => ['tag' => 'SERVICE_MESSAGE', 'caption' => 'Service message'],
            10 => ['tag' => 'SUN', 'caption' => 'Sun'],
            11 => ['tag' => 'MOON', 'caption' => 'Moon'],
            12 => ['tag' => 'WIND', 'caption' => 'Wind'],
            13 => ['tag' => 'CLOUD', 'caption' => 'Cloud'],
            14 => ['tag' => 'THUNDERSTORM', 'caption' => 'Thunderstorm'],
            15 => ['tag' => 'DRIZZLE', 'caption' => 'Drizzle'],
            16 => ['tag' => 'CLOUD_AND_MOOON', 'caption' => 'Cloud and moon'],
            17 => ['tag' => 'RAIN', 'caption' => 'Rain'],
            18 => ['tag' => 'SNOW', 'caption' => 'Snow'],
            19 => ['tag' => 'CLOUD_AND_SUN', 'caption' => 'Cloud and sun'],
            20 => ['tag' => 'CLOUD_SUN_AND_RAIN', 'caption' => 'Cloud, sun and rain'],
            21 => ['tag' => 'SNOWFLAKE', 'caption' => 'Snowflake'],
            22 => ['tag' => 'RAINDROP', 'caption' => 'Raindrop'],
            23 => ['tag' => 'FLAME', 'caption' => 'Flame'],
            24 => ['tag' => 'WINDOW_OPEN', 'caption' => 'Window open'],
            25 => ['tag' => 'SHUTTERS', 'caption' => 'Shutters'],
            26 => ['tag' => 'ECO', 'caption' => 'Eco'],
            27 => ['tag' => 'PROTECTION_DEACTIVATED', 'caption' => 'Disarmed'],
            28 => ['tag' => 'EXTERNAL_PROTECTION', 'caption' => 'External protection'],
            29 => ['tag' => 'INTERNAL_PROTECTION', 'caption' => 'Internal protection'],
            30 => ['tag' => 'BELL', 'caption' => 'Bell'],
            31 => ['tag' => 'CLOCK', 'caption' => 'Clock'],
        ];

        return $opts;
    }

    private function IconFormatted($val, $useCaption)
    {
        $maps = $this->IconMapping();
        if (isset($maps[$val]) == false) {
            return false;
        }
        $map = $maps[$val];
        return $useCaption ? $this->Translate($map['caption']) : $map['tag'];
    }

    private function IconDecode(string $ident)
    {
        $icon = 0;
        $maps = $this->IconMapping();
        foreach ($maps as $index => $map) {
            if ($map['tag'] == strtolower($ident)) {
                $icon = $index;
                break;
            }
        }
        return $icon;
    }

    public function IconAsOptions()
    {
        $maps = $this->IconMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $this->Translate($e['caption']),
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function SoundMapping()
    {
        $opts = [
            -1  => ['tag' => 'NONE', 'caption' => 'None'],
            0   => ['tag' => 'LOW_BATTERY', 'caption' => 'short-short-short'],
            1   => ['tag' => 'DISARMED', 'caption' => 'long-short'],
            2   => ['tag' => 'INTERNALLY_ARMED', 'caption' => 'long-short-short'],
            3   => ['tag' => 'EXTERNALLY_ARMED', 'caption' => 'long-short'],
            4   => ['tag' => 'DELAYED_INTERNALLY_ARMED', 'caption' => 'short-short'],
            5   => ['tag' => 'DELAYED_EXTERNALLY_ARMED', 'caption' => 'short'],
            6   => ['tag' => 'EVENT', 'caption' => 'medium'],
            7   => ['tag' => 'ERROR', 'caption' => 'long'],
        ];

        return $opts;
    }

    private function SoundFormatted($val, $useCaption)
    {
        $maps = $this->SoundMapping();
        if (isset($maps[$val]) == false) {
            return false;
        }
        $map = $maps[$val];
        return $useCaption ? $this->Translate($map['caption']) : $map['tag'];
    }

    private function SoundDecode(string $ident)
    {
        $sound = 0;
        $maps = $this->SoundMapping();
        foreach ($maps as $index => $map) {
            if ($map['tag'] == strtolower($ident)) {
                $sound = $index;
                break;
            }
        }
        return $sound;
    }

    public function SoundAsOptions()
    {
        $maps = $this->SoundMapping();
        $opts = [];
        foreach ($maps as $u => $e) {
            $opts[] = [
                'caption' => $this->Translate($e['caption']),
                'value'   => $u,
            ];
        }
        return $opts;
    }

    private function RepetitionFormatted($val, $useCaption)
    {
        if ($val < 0 || $val > 15) {
            return false;
        }
        if ($val == 0) {
            return $useCaption ? $this->Translate('no') : '0';
        }
        if ($val == 15) {
            return $useCaption ? $this->Translate('infinite') : '15';
        }
        return (string) $val;
    }

    private function IntervalFormatted($val, $useCaption)
    {
        if ($val < 1 || $val > 15) {
            return false;
        }
        return (string) $val;
    }
}
