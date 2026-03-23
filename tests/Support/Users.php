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
        ->withRole('Staff Access')
        ->create();
}

function crewChaplain()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::CrewMember, 'Test Chaplain Crewman')
        ->withRole('Staff Access')
        ->create();
}

function crewEngineer()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::CrewMember, 'Test Engineer Crewman')
        ->withRole('Staff Access')
        ->create();
}

function crewQuartermaster()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::CrewMember, 'Test Quartermaster Crewman')
        ->withRole('Staff Access')
        ->create();
}

function crewSteward()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::CrewMember, 'Test Steward Crewman')
        ->withRole('Staff Access')
        ->create();
}

// == Officer Positions ==
function officerCommand()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Command, StaffRank::Officer, 'Test Command Officer')
        ->withRole('Staff Access')
        ->create();
}

function officerChaplain()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::Officer, 'Test Chaplain Officer')
        ->withRole('Staff Access')
        ->create();
}

function officerEngineer()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::Officer, 'Test Engineer Officer')
        ->withRole('Staff Access')
        ->create();
}

function officerQuartermaster()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::Officer, 'Test Quartermaster Officer')
        ->withRole('Staff Access')
        ->create();
}

function officerSteward()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::Officer, 'Test Steward Officer')
        ->withRole('Staff Access')
        ->create();
}

// == Officer Positions ==
function jrCrewCommand()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Command, StaffRank::JrCrew, 'Test Command JrCrew')
        ->withRole('Staff Access')
        ->create();
}

function jrCrewChaplain()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Chaplain, StaffRank::JrCrew, 'Test Chaplain JrCrew')
        ->withRole('Staff Access')
        ->create();
}

function jrCrewEngineer()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Engineer, StaffRank::JrCrew, 'Test Engineer JrCrew')
        ->withRole('Staff Access')
        ->create();
}

function jrCrewQuartermaster()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Quartermaster, StaffRank::JrCrew, 'Test Quartermaster JrCrew')
        ->withRole('Staff Access')
        ->create();
}

function jrCrewSteward()
{
    return User::factory()
        ->withMembershipLevel(MembershipLevel::Resident)
        ->withStaffPosition(StaffDepartment::Steward, StaffRank::JrCrew, 'Test Steward JrCrew')
        ->withRole('Staff Access')
        ->create();
}
