<?php

use App\Enums\MembershipLevel;
use App\Enums\StaffDepartment;
use App\Enums\StaffRank;
use App\Models\User;

// == Membership Levels == //
function membershipDrifter()
{
    return User::factory()->withMembershipLevel(MembershipLevel::Drifter)->create();
}

function membershipStowaway()
{
    return User::factory()->withMembershipLevel(MembershipLevel::Stowaway)->create();
}

function membershipTraveler()
{
    return User::factory()->withMembershipLevel(MembershipLevel::Traveler)->create();
}

function membershipResident()
{
    return User::factory()->withMembershipLevel(MembershipLevel::Resident)->create();
}

function membershipCitizen()
{
    return User::factory()->withMembershipLevel(MembershipLevel::Citizen)->create();
}

// == Crew Member Positions ==
function crewCommand()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Command, StaffRank::CrewMember, 'Test Command Crewman')
        ->create();
}

function crewChaplain()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember, 'Test Chaplain Crewman')
        ->create();
}

function crewEngineer()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember, 'Test Engineer Crewman')
        ->create();
}

function crewQuartermaster()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Test Quartermaster Crewman')
        ->create();
}

function crewSteward()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::CrewMember, 'Test Steward Crewman')
        ->create();
}

// == Officer Positions ==
function officerCommand()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer, 'Test Command Officer')
        ->create();
}

function officerChaplain()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer, 'Test Chaplain Officer')
        ->create();
}

function officerEngineer()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::Officer, 'Test Engineer Officer')
        ->create();
}

function officerQuartermaster()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer, 'Test Quartermaster Officer')
        ->create();
}

function officerSteward()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::Officer, 'Test Steward Officer')
        ->create();
}

// == Officer Positions ==
function jrCrewCommand()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew, 'Test Command JrCrew')
        ->create();
}

function jrCrewChaplain()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew, 'Test Chaplain JrCrew')
        ->create();
}

function jrCrewEngineer()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew, 'Test Engineer JrCrew')
        ->create();
}

function jrCrewQuartermaster()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::JrCrew, 'Test Quartermaster JrCrew')
        ->create();
}

function jrCrewSteward()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::JrCrew, 'Test Steward JrCrew')
        ->create();
}
