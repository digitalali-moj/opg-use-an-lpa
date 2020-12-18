@actor @cannotFindLpa
Feature: What happens next after requesting an activation key
  As a user
  I want to know what happens next on requesting an activation key
  So that I know the provided data matched or failed

  Background:
    Given I am a user of the lpa application
    And I am currently signed in

  @ui
  Scenario: The user can go back to dashboard page from activation key sent confirmation page
    Given I have requested an activation key with valid details
    When I am on cannot find LPA page after checking provided answers
    And I request to go back to lpas page
    Then I am taken to the dashboard page

  @ui
  Scenario: The user can sign out from activation key sent confirmation page
    Given I have requested an activation key with valid details
    When I am on cannot find LPA page after checking provided answers
    And I sign out from the page
    Then I am taken to complete a satisfaction survey