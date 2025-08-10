<?php

describe('Meeting Show - Loading', function () {
    // Load page and views

    // Loads model data
})->todo(issue: 56);

describe('Meeting Show - Page Data', function () {
    // Handles invalid meeting
})->todo(issue: 56);

describe('Meeting Show - Attendance', function () {
    // Shows the modal to select users for attendance

    // Shows users who attended the meeting
})->todo(issue: 55);

describe('Meeting Show - Notes', function () {
    // Show a section for each department

    // Show blank notes for each department section

    // Create the notes when data is added to the note
    // Saves the note sections rapidly (every few seconds while being worked on)
    // Locks the note section when the user starts editing
    // Unlocks the note section after the lock expires
    // Unlocks the note section when the user is done editing
})->todo(issue: 13);

describe('Meeting Show - Action Items', function () {
    // Each department section has a todo list that can be added

    // The meeting should show existing todo items that are not completed

    // Have a button that imports outstanding tasks

    // Have a button that imports tasks completed since a selected meeting
})->todo(issue: 28);
