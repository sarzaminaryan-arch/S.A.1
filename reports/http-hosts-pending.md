# HTTP hosts pending protocol review

No live requests were made. The theme now upgrades only an explicit HTTPS allowlist during rendered-content processing; Wikipedia/Wikimedia URLs remain present and are not removed.

| Host | Current repository evidence | Decision |
| --- | --- | --- |
| `dorood-gov.ir` | Existing ready-article references and inline citation | Left as HTTP; domain/endpoint HTTPS behavior was not verified. |
| `funholiday.ir` | Existing ready-article reference | Left as HTTP; third-party host was not verified. |

The allowlisted content hosts are `www.iew.ir`, `www.trjums.ac.ir`, and `lorestan.irib.ir`; Wikipedia/Wikimedia domains are also allowed. These upgrades are a rendering-time scheme substitution only, not a claim that every individual URL was live-tested. Other repository occurrences of `http://` are XML/SVG namespaces or the GPL license URI, not content hyperlinks.
