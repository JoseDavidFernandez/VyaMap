# VyaMap — ADR-001: Separate Map Contexts

## Status

Accepted

## Decision

VyaMap will use different map experiences for different travel concepts rather than displaying all geographic information on one map.

## Context

VyaMap stores several geographic concepts: visited countries/cities, airports, flights, places and geolocated photos. Showing all of them simultaneously would make the main map difficult to understand as the amount of data grows.

## Decision details

- Dashboard: country map showing visited countries.
- Flights: map showing historical flight routes.
- Country/City: contextual map showing cities and places.
- Trip: contextual map combining geographic elements belonging to a specific trip.

## Technical approach

These experiences will share reusable map infrastructure and configurable layers instead of implementing independent map systems.

## Consequences

### Positive

- Clearer product semantics.
- Better scalability as travel history grows.
- Reusable Leaflet/map infrastructure.
- Each screen can optimize the map for its purpose.

### Trade-off

The map system needs a layer/context abstraction instead of one generic "show everything" component.

## Related domain rule

A country is visited when the user has at least one `Visit` for a city belonging to that country. Flights do not imply a country visit.
