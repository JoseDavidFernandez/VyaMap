# VyaMap — Map System

## Decision

VyaMap will not use a single map to display every type of travel information.

The application will use a common map infrastructure with different visual layers and contexts.

## Map contexts

### Dashboard — Country Map

Purpose: show the user's travel history geographically.

Primary layer:
- Countries visited

A country is considered visited when the user has at least one `Visit` for a city belonging to that country.

### Flights — Flight Map

Purpose: visualize the user's flight history.

Primary layers:
- Airports
- Flight routes

The current Leaflet implementation is the technical base for this map.

### Country / City — Places Map

Purpose: explore what the user has done in a specific geographic area.

Possible layers:
- Cities
- Places
- Photos with geographic information

### Trip — Trip Map

Purpose: reconstruct a concrete trip.

Possible layers:
- Cities visited during the trip
- Flights belonging to the trip
- Places
- Geolocated photos

## Shared architecture

The application should not create a separate mapping implementation for every page.

VyaMap should have reusable map infrastructure with configurable layers.

```text
                    TravelMap
                       |
          +------------+------------+
          |            |            |
      Countries      Flights       Places
          |            |            |
       GeoJSON       Routes       Markers
```

Examples:

```text
Dashboard
  -> Countries

Flights
  -> Flights

Country / City
  -> Cities + Places

Trip
  -> Cities + Flights + Places + Photos
```

## Architectural rule

Do not overload the Dashboard map with flights, places, photos and every other type of information.

The Dashboard map should answer:

> Where have I been?

The Flight Map should answer:

> Where have I flown?

Contextual maps should answer:

> What did I do there?
