@extends('layouts.app')

@section('content')
    <div class="platform-guide-page">
        <x-admin.page-header
            title="System flow guide"
            description="How an institution moves from registration through approval, subscription, and day-to-day use."
        />

        <div class="platform-guide-callout" role="note">
            <span class="platform-guide-callout-icon" aria-hidden="true"><i class="fa-solid fa-circle-info"></i></span>
            <p><strong>Platform administrator view.</strong> This guide describes the application workflow. Tenant users only see their own institution’s modules and data.</p>
        </div>

        <section class="admin-card platform-guide-section" aria-labelledby="guide-registration-title">
            <div class="platform-guide-heading">
                <span class="platform-guide-number">01</span>
                <div>
                    <h2 id="guide-registration-title">Registration and approval</h2>
                    <p>New institutions remain outside the dashboard until the platform administrator reviews them.</p>
                </div>
            </div>

            <div class="platform-guide-flow" aria-label="Registration flow">
                <div class="platform-flow-lane">
                    <div class="platform-flow-node">
                        <span class="platform-flow-step">Registration form</span>
                        <strong>Institution applies</strong>
                        <small>Owner, institution, and contact details are submitted.</small>
                    </div>
                    <span class="platform-flow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    <div class="platform-flow-node">
                        <span class="platform-flow-step">Pending</span>
                        <strong>Account is under review</strong>
                        <small>The owner, tenant membership, and main campus are created.</small>
                    </div>
                    <span class="platform-flow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    <div class="platform-flow-node platform-flow-node--decision">
                        <span class="platform-flow-step">Platform admin</span>
                        <strong>Review application</strong>
                        <small>Check the tenant record and choose approve or reject.</small>
                    </div>
                </div>

                <div class="platform-flow-outcomes">
                    <article class="platform-flow-outcome is-success">
                        <div class="platform-flow-outcome-heading">
                            <span><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                            <div><strong>Approve</strong><small>Institution is ready to onboard.</small></div>
                        </div>
                        <div class="platform-flow-mini-steps">
                            <span>Set approved status</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Start 14-day trial</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Seed editable masters</span>
                        </div>
                        <p>The owner can now sign in. Standard campus, academic, and finance setup is created for that tenant.</p>
                    </article>

                    <article class="platform-flow-outcome is-danger">
                        <div class="platform-flow-outcome-heading">
                            <span><i class="fa-solid fa-xmark" aria-hidden="true"></i></span>
                            <div><strong>Reject</strong><small>Registration does not proceed.</small></div>
                        </div>
                        <p>The decision and reviewer are recorded. The owner cannot enter a dashboard and is told to contact the platform administrator.</p>
                    </article>
                </div>
            </div>

            <div class="platform-guide-note">
                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                <p><strong>What approval seeds:</strong> all tenants get an academic year, financial year, and standard expense categories. Schools also get grade levels and A/B/C sections. Colleges get sample departments, courses, and six semesters. Tenant administrators can edit or remove unused defaults.</p>
            </div>
        </section>

        <section class="admin-card platform-guide-section" aria-labelledby="guide-access-title">
            <div class="platform-guide-heading">
                <span class="platform-guide-number">02</span>
                <div>
                    <h2 id="guide-access-title">Sign-in and access control</h2>
                    <p>Platform access and tenant access follow separate authorization paths.</p>
                </div>
            </div>

            <div class="platform-guide-flow" aria-label="Authentication and access flow">
                <div class="platform-flow-lane">
                    <div class="platform-flow-node">
                        <span class="platform-flow-step">Sign in</span>
                        <strong>Check credentials</strong>
                        <small>Inactive accounts are denied.</small>
                    </div>
                    <span class="platform-flow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    <div class="platform-flow-node platform-flow-node--decision">
                        <span class="platform-flow-step">Identify account</span>
                        <strong>Platform admin or tenant user?</strong>
                        <small>The account type selects the protected area.</small>
                    </div>
                </div>

                <div class="platform-flow-outcomes">
                    <article class="platform-flow-outcome is-platform">
                        <div class="platform-flow-outcome-heading">
                            <span><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>
                            <div><strong>Platform administrator</strong><small>Platform middleware checks the super admin flag.</small></div>
                        </div>
                        <div class="platform-flow-mini-steps">
                            <span>Tenants</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Plans and subscriptions</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Platform guide and audit</span>
                        </div>
                    </article>

                    <article class="platform-flow-outcome is-tenant">
                        <div class="platform-flow-outcome-heading">
                            <span><i class="fa-solid fa-school" aria-hidden="true"></i></span>
                            <div><strong>Tenant user</strong><small>Requires an active membership in an approved tenant.</small></div>
                        </div>
                        <div class="platform-flow-mini-steps">
                            <span>Choose active tenant</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Set tenant context</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Check subscription and permissions</span>
                        </div>
                    </article>
                </div>
            </div>

            <div class="platform-guide-note is-warning">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <p><strong>Tenant boundaries:</strong> tenant context scopes tenant-owned records. Role permissions control modules, and branch assignments limit campus records. Suspended, cancelled, or expired tenants are redirected to billing; billing and sign-out remain available.</p>
            </div>
        </section>

        <section class="admin-card platform-guide-section" aria-labelledby="guide-subscription-title">
            <div class="platform-guide-heading">
                <span class="platform-guide-number">03</span>
                <div>
                    <h2 id="guide-subscription-title">Trial, renewal, and expiry</h2>
                    <p>Approval starts the trial. Tenant owners or administrators manage renewals from billing.</p>
                </div>
            </div>

            <div class="platform-guide-flow" aria-label="Subscription renewal flow">
                <div class="platform-flow-lane">
                    <div class="platform-flow-node">
                        <span class="platform-flow-step">Trial or current plan</span>
                        <strong>Tenant uses the application</strong>
                        <small>Plan limits and end date apply to the tenant.</small>
                    </div>
                    <span class="platform-flow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                    <div class="platform-flow-node">
                        <span class="platform-flow-step">Billing</span>
                        <strong>Choose plan and billing interval</strong>
                        <small>Select monthly or yearly billing when offered.</small>
                    </div>
                </div>

                <div class="platform-flow-outcomes">
                    <article class="platform-flow-outcome is-tenant">
                        <div class="platform-flow-outcome-heading">
                            <span><i class="fa-solid fa-credit-card" aria-hidden="true"></i></span>
                            <div><strong>Online checkout</strong><small>Razorpay is used when configured and the plan has a price.</small></div>
                        </div>
                        <div class="platform-flow-mini-steps">
                            <span>Create order</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Verify signature and captured amount</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Activate subscription</span>
                        </div>
                    </article>

                    <article class="platform-flow-outcome is-platform">
                        <div class="platform-flow-outcome-heading">
                            <span><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                            <div><strong>Manual payment reference</strong><small>Platform administrator reviews the renewal request.</small></div>
                        </div>
                        <div class="platform-flow-mini-steps">
                            <span>Submit reference</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Platform review</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                            <span>Approve or reject</span>
                        </div>
                    </article>
                </div>
            </div>

            <div class="platform-guide-note is-warning">
                <i class="fa-solid fa-hourglass-end" aria-hidden="true"></i>
                <p><strong>When a subscription expires:</strong> tenant modules are blocked and the user is sent to billing to renew. Platform administrators can review manual requests and manage the tenant’s access status separately from its registration approval.</p>
            </div>
        </section>

        <section class="admin-card platform-guide-section" aria-labelledby="guide-team-title">
            <div class="platform-guide-heading">
                <span class="platform-guide-number">04</span>
                <div>
                    <h2 id="guide-team-title">Tenant setup and team workflow</h2>
                    <p>After approval, the institution manages its own campuses, people, and school or college operations.</p>
                </div>
            </div>

            <div class="platform-flow-lane platform-flow-lane--three" aria-label="Tenant operations flow">
                <div class="platform-flow-node">
                    <span class="platform-flow-step">Set up</span>
                    <strong>Review standard masters</strong>
                    <small>Configure campuses, years, classes or courses, and sections.</small>
                </div>
                <span class="platform-flow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                <div class="platform-flow-node">
                    <span class="platform-flow-step">Invite</span>
                    <strong>Assign roles and branches</strong>
                    <small>Owner/admin invites staff, teachers, fee staff, students, or parents.</small>
                </div>
                <span class="platform-flow-arrow" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span>
                <div class="platform-flow-node">
                    <span class="platform-flow-step">Operate</span>
                    <strong>Use tenant modules</strong>
                    <small>Manage admissions, attendance, academics, fees, finance, and reports.</small>
                </div>
            </div>

            <div class="platform-guide-footnote">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                <span>Invitation links expire after seven days. Student and parent invitations can be linked to their portal records; staff access can be limited to selected branches.</span>
            </div>
        </section>
    </div>
@endsection
