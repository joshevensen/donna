# apple/

Create the Xcode project here (Xcode → New Project). It can't be generated outside Xcode reliably.

## Project

- **Name:** `Donna`, saved in this folder (`apple/Donna.xcodeproj`)
- **Template:** Multiplatform → App (SwiftUI)

## Targets

| Target | Platform | Purpose |
|---|---|---|
| `Donna` | iOS, iPadOS, macOS | The main app — chat, files, quick capture, App Intents |
| `Donna Watch` | watchOS | Capture only — dictation into the conversation |
| `DonnaNode` | macOS (headless, login item) | Home Node agent — Messages, EventKit, Shortcuts, on-device model |

Put shared code (API client, models) in a local Swift package (`apple/DonnaKit`) so all three targets use it.

## Notes

- Dark mode only
- Siri via App Intents (iOS 27+)
- Push via APNs — enable the Push Notifications capability on `Donna`
