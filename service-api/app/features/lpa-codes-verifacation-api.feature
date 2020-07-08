@integration
Feature: Check LPA Codes API Response
  For code validation

#  @pact
#  Scenario: The user can add an LPA to their account
#    When I request to add an LPA
#    Then I should be told my code is valid

  Background:
    Given I have been given access to use an LPA via credentials
    And I am a user of the lpa application
    And I am currently signed in

  @pact
  Scenario: The user can add an LPA to their account
    Given I am on the add an LPA page
    When I request to add an LPA with valid details
    Then The correct LPA is found and I can confirm to add it
    And The LPA is successfully added

