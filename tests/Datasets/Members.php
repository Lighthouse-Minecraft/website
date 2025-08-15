<?php

dataset('memberAll', [
    'Membership Drifter' => fn () => membershipDrifter(),
    'Membership Stowaway' => fn () => membershipStowaway(),
    'Membership Traveler' => fn () => membershipTraveler(),
    'Membership Resident' => fn () => membershipResident(),
    'Membership Citizen' => fn () => membershipCitizen(),
]);

dataset('memberAtLeastCitizen', [
    'Membership Citizen' => fn () => membershipCitizen(),
]);

dataset('memberAtLeastResident', [
    'Membership Citizen' => fn () => membershipCitizen(),
    'Membership Resident' => fn () => membershipResident(),
]);

dataset('memberAtLeastTraveler', [
    'Membership Citizen' => fn () => membershipCitizen(),
    'Membership Resident' => fn () => membershipResident(),
    'Membership Traveler' => fn () => membershipTraveler(),
]);

dataset('memberAtLeastStowaway', [
    'Membership Citizen' => fn () => membershipCitizen(),
    'Membership Resident' => fn () => membershipResident(),
    'Membership Traveler' => fn () => membershipTraveler(),
    'Membership Stowaway' => fn () => membershipStowaway(),
]);

dataset('memberAtLeastDrifter', [
    'Membership Citizen' => fn () => membershipCitizen(),
    'Membership Resident' => fn () => membershipResident(),
    'Membership Traveler' => fn () => membershipTraveler(),
    'Membership Stowaway' => fn () => membershipStowaway(),
    'Membership Drifter' => fn () => membershipDrifter(),
]);

dataset('memberAtMostDrifter', [
    'Membership Drifter' => fn () => membershipDrifter(),
]);

dataset('memberAtMostStowaway', [
    'Membership Drifter' => fn () => membershipDrifter(),
    'Membership Stowaway' => fn () => membershipStowaway(),
]);

dataset('memberAtMostTraveler', [
    'Membership Drifter' => fn () => membershipDrifter(),
    'Membership Stowaway' => fn () => membershipStowaway(),
    'Membership Traveler' => fn () => membershipTraveler(),
]);

dataset('memberAtMostResident', [
    'Membership Drifter' => fn () => membershipDrifter(),
    'Membership Stowaway' => fn () => membershipStowaway(),
    'Membership Traveler' => fn () => membershipTraveler(),
    'Membership Resident' => fn () => membershipResident(),
]);

dataset('memberAtMostCitizen', [
    'Membership Drifter' => fn () => membershipDrifter(),
    'Membership Stowaway' => fn () => membershipStowaway(),
    'Membership Traveler' => fn () => membershipTraveler(),
    'Membership Resident' => fn () => membershipResident(),
    'Membership Citizen' => fn () => membershipCitizen(),
]);
