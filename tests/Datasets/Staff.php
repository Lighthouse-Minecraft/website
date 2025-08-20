<?php

dataset('officers', [
    'Officer Command' => fn () => officerCommand(),
    'Officer Chaplain' => fn () => officerChaplain(),
    'Officer Engineer' => fn () => officerEngineer(),
    'Officer Quartermaster' => fn () => officerQuartermaster(),
    'Officer Steward' => fn () => officerSteward(),
]);

dataset('crewmembers', [
    'Crew Member Command' => fn () => crewCommand(),
    'Crew Member Chaplain' => fn () => crewChaplain(),
    'Crew Member Engineer' => fn () => crewEngineer(),
    'Crew Member Quartermaster' => fn () => crewQuartermaster(),
    'Crew Member Steward' => fn () => crewSteward(),
]);

dataset('jrcrew', [
    'Jr Crew Command' => fn () => jrCrewCommand(),
    'Jr Crew Chaplain' => fn () => jrCrewChaplain(),
    'Jr Crew Engineer' => fn () => jrCrewEngineer(),
    'Jr Crew Quartermaster' => fn () => jrCrewQuartermaster(),
    'Jr Crew Steward' => fn () => jrCrewSteward(),
]);

dataset('rankAtLeastCrewMembers', [
    'Officer Command' => fn () => officerCommand(),
    'Officer Chaplain' => fn () => officerChaplain(),
    'Officer Engineer' => fn () => officerEngineer(),
    'Officer Quartermaster' => fn () => officerQuartermaster(),
    'Officer Steward' => fn () => officerSteward(),
    'Crew Member Command' => fn () => crewCommand(),
    'Crew Member Chaplain' => fn () => crewChaplain(),
    'Crew Member Engineer' => fn () => crewEngineer(),
    'Crew Member Quartermaster' => fn () => crewQuartermaster(),
    'Crew Member Steward' => fn () => crewSteward(),
]);

dataset('rankAtLeastJrCrew', [
    'Officer Command' => fn () => officerCommand(),
    'Officer Chaplain' => fn () => officerChaplain(),
    'Officer Engineer' => fn () => officerEngineer(),
    'Officer Quartermaster' => fn () => officerQuartermaster(),
    'Officer Steward' => fn () => officerSteward(),
    'Crew Member Command' => fn () => crewCommand(),
    'Crew Member Chaplain' => fn () => crewChaplain(),
    'Crew Member Engineer' => fn () => crewEngineer(),
    'Crew Member Quartermaster' => fn () => crewQuartermaster(),
    'Crew Member Steward' => fn () => crewSteward(),
    'Jr Crew Command' => fn () => jrCrewCommand(),
    'Jr Crew Chaplain' => fn () => jrCrewChaplain(),
    'Jr Crew Engineer' => fn () => jrCrewEngineer(),
    'Jr Crew Quartermaster' => fn () => jrCrewQuartermaster(),
    'Jr Crew Steward' => fn () => jrCrewSteward(),
]);

dataset('rankAtMostJrCrew', [
    'Jr Crew Command' => fn () => jrCrewCommand(),
    'Jr Crew Chaplain' => fn () => jrCrewChaplain(),
    'Jr Crew Engineer' => fn () => jrCrewEngineer(),
    'Jr Crew Quartermaster' => fn () => jrCrewQuartermaster(),
    'Jr Crew Steward' => fn () => jrCrewSteward(),
]);

dataset('rankAtMostCrewMembers', [
    'Jr Crew Command' => fn () => jrCrewCommand(),
    'Jr Crew Chaplain' => fn () => jrCrewChaplain(),
    'Jr Crew Engineer' => fn () => jrCrewEngineer(),
    'Jr Crew Quartermaster' => fn () => jrCrewQuartermaster(),
    'Jr Crew Steward' => fn () => jrCrewSteward(),
    'Crew Member Command' => fn () => crewCommand(),
    'Crew Member Chaplain' => fn () => crewChaplain(),
    'Crew Member Engineer' => fn () => crewEngineer(),
    'Crew Member Quartermaster' => fn () => crewQuartermaster(),
    'Crew Member Steward' => fn () => crewSteward(),
]);
