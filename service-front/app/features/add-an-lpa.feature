@actor @addLpa
Feature: Add an LPA
  As a user
  If I have created an account
  I can add an LPA to my account

  Background:
    Given I am a user of the lpa application
    And I am currently signed in
    And I have been given access to use an LPA via credentials

  @integration @ui
  Scenario: The user can add an LPA to their account
    Given I am on the add an LPA page
    When I request to add an LPA with valid details
    Then The correct LPA is found and I can confirm to add it
    And The LPA is successfully added

  @integration @ui
  Scenario: The user cannot add an LPA to their account as it does not exist
    Given I am on the add an LPA page
    When I request to add an LPA that does not exist
    Then The LPA is not found
    And I request to go back and try again

  @integration @ui
  Scenario: The user can cancel adding their LPA
    Given I am on the add an LPA page
    When I fill in the form and click the cancel button
    Then I am taken back to the dashboard page
    And The LPA has not been added

  @ui
  Scenario Outline: The user cannot add an LPA with an invalid passcode
    Given I am on the add an LPA page
    When I request to add an LPA with an invalid passcode format of "<passcode>"
    Then I am told that my input is invalid because <reason>

    Examples:
      | passcode | reason |
      | T3ST PA22C0D3 | Your passcode must only include letters, numbers and dashes |
      | T3ST PA22-C0D3 | Your passcode must only include letters, numbers and dashes |
      | T3STP*22C0!? | Your passcode must only include letters, numbers and dashes |
      | T3ST - PA22 - C0D3 | Your passcode must be 12 characters long |
      | T3STPA22C0D | Your passcode must be 12 characters long |
      |  | Enter your one-time passcode |

  @ui
  Scenario Outline: The user cannot add an LPA with an invalid reference number
    Given I am on the add an LPA page
    When I request to add an LPA with an invalid reference number format of "<referenceNo>"
    Then I am told that my input is invalid because <reason>

    Examples:
      | referenceNo | reason |
      | 7000-00000001 | The reference number must only include numbers |
      | 7000-0000 0001 | The reference number must only include numbers |
      | 7000-0000-ABC! | The reference number must only include numbers |
      | 7000-0000-00011 | The LPA reference number must be 12 numbers long |
      | 70000000000 | The LPA reference number must be 12 numbers long |
      |  | Enter a reference number |

  @ui
  Scenario Outline: The user cannot add an LPA with an invalid DOB
    Given I am on the add an LPA page
    When I request to add an LPA with an invalid DOB format of "<day>" "<month>" "<year>"
    Then I am told that my input is invalid because <reason>

    Examples:
      | day | month | year | reason |
      | 32 | 05 | 1975 | Enter a real date of birth |
      | 10 | 13 | 1975 | Enter a real date of birth |
      | XZ | 10 | 1975 | Enter a real date of birth |
      | 10 | 05 | 3000 | Your date of birth must be in the past |


