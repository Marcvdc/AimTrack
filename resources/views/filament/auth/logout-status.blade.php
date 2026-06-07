@if (session('status'))
    <div class="fi-logout-status" style="margin-top: 1.5rem;">
        <div
            class="fi-logout-status__flash"
            style="display:flex;gap:0.75rem;align-items:flex-start;padding:1rem 1.25rem;border-radius:0.75rem;background:rgba(76,175,80,0.12);border:1px solid rgba(76,175,80,0.3);color:#1b4332;font-size:0.95rem;flex-wrap:wrap;"
        >
            <span
                class="fi-logout-status__icon"
                aria-hidden="true"
                style="width:2rem;height:2rem;display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:#1b4332;color:#fff;font-weight:600;font-size:1rem;"
            >
                ✓
            </span>

            <div>
                <p class="fi-logout-status__title" style="margin:0;font-weight:600;">
                    {{ session('status') }}
                </p>
                <p class="fi-logout-status__body" style="margin:0.25rem 0 0;font-size:0.9rem;color:#1b4332cc;">
                    Je bent veilig uitgelogd. Log opnieuw in om verder te gaan.
                </p>
            </div>
        </div>
    </div>
@endif
