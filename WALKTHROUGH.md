# Walkthrough

This walkthrough takes you through the system end-to-end: logging in, setting patient context, authoring a form, completing it, exporting the submission as FHIR, observing the role-based access controls and verifying that audit logging and git integration behave as documented.

## 1. Login

The request should be redirected to `/login.php` because no session exists.

Log in as the admin user with the credentials `admin` / `admin123`.

## 2. Patient context

Once authenticated, the next screen is the MRN entry page. Enter `P001` and submit. The session is now bound to Alice Walker for the remainder of the session, and the MRN appears in the top navigation banner as a persistent reminder of the active patient.

To verify that patient context is enforced rather than optional, try navigating directly to a protected URL such as `/form-list.php` without an MRN set. The request is redirected back to MRN entry. This guard runs on every protected page, not just the form list.

## 3. Build a form (admin only)

Open the Forms dropdown in the navigation and select **Form Builder**.

Create a form with at least two fields. For example:

- A text field labelled "Reason for visit".
- A date field labelled "Visit date", marked as required.

Click **Save Form**. The page returns to the form list with a success notice confirming the form was created.

## 4. Complete the form

Open **Complete Form** from the Forms dropdown and select the form you just built. Fill in the fields.

Click **Save Draft** to persist the partially completed form without running the required-field validation. Refresh the page and the draft values repopulate from the database, confirming that drafts round-trip through `form_submissions` correctly.

Now enter a full name in the **Electronic Signature** block, tick the attestation checkbox and click **Save and Complete**. The request is redirected to the dashboard with the new submission ID displayed.

## 5. View the submission

Open **Case File** in the navigation. The submission just created appears at the top of the list. Click it to open the read-only submission view, which renders the completed form alongside the signature block.

## 6. Export as FHIR

From the submission view, click **Export FHIR**. The system renders a FHIR R4 `QuestionnaireResponse` as JSON. Click **Download JSON** to save the resource locally.

The exported resource contains `resourceType`, `status`, `subject`, `authored` and an `item[]` tree which mirrors the form's fields and sections.

## 7. Role separation

Log out, then log back in as the demo staff user with the credentials `staff` / `staff123`. Enter MRN `P001` again to set patient context.

Open the Forms dropdown. The **Form Builder** link is no longer rendered. To verify that the control is enforced at the page controller rather than only in the navigation, try navigating directly to `/form-builder.php`. The request is redirected to the dashboard with a permission error flash message. Only admin accounts can build forms.

## 8. Account lockout

Log out. On the login page, attempt to log in as `admin` with an incorrect password five times consecutively. On the fifth failure the response changes to:

> Account locked until: HH:MM. Too many failed attempts.

The account is now locked for fifteen minutes. To continue testing, either wait for the lockout to expire or clear it manually in the database:

```sql
UPDATE staff_users SET locked_until = NULL WHERE username = 'admin';
```

## 9. Audit log

Every security-relevant action in the session above is recorded in the audit log. To inspect the entries, run:

```sql
SELECT created_at, action, user_id, details
  FROM audit_log
 ORDER BY log_id DESC
 LIMIT 20;
```

The result set should contain `login_success`, `patient_select`, `form_create`, `form_submit`, `form_submission_fhir_export` and `logout` entries from the main walkthrough, alongside at least five `login_failed` rows and a `login_locked` row produced during the lockout test.

## 10. Git integration

Each time a form is finalised, the system creates a branch on the local repository containing the generated PHP file. To list the branches:
