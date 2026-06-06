"""Compatibility shim: starlette 0.27's TestClient passes ``app=`` to
``httpx.Client``, which httpx>=0.28 no longer accepts. Translate it into an
explicit ASGI transport so the existing TestClient-based tests keep working.
"""
from __future__ import annotations

import httpx

_orig_init = httpx.Client.__init__


def _patched_init(self, *args, app=None, **kwargs):
    if app is not None and kwargs.get("transport") is None:
        kwargs["transport"] = httpx.ASGITransport(app=app, raise_app_exceptions=True)
    _orig_init(self, *args, **kwargs)


httpx.Client.__init__ = _patched_init
