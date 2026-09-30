"""Helper di reificazione booleana e slack, condivisi da solver.py e dai
moduli vincoli/*.py."""

import itertools

from ortools.sat.python import cp_model

_contatore = itertools.count()


def reify_and(model: cp_model.CpModel, lits: list) -> cp_model.IntVar:
    """Crea un BoolVar z == AND(lits). Lista vuota => z fissata a True."""
    z = model.NewBoolVar(f'and_{next(_contatore)}')
    if not lits:
        model.Add(z == 1)
        return z
    model.AddBoolAnd(lits).OnlyEnforceIf(z)
    model.AddBoolOr([l.Not() for l in lits] + [z])
    return z


def reify_or(model: cp_model.CpModel, lits: list) -> cp_model.IntVar:
    """Crea un BoolVar z == OR(lits). Lista vuota => z fissata a False."""
    z = model.NewBoolVar(f'or_{next(_contatore)}')
    if not lits:
        model.Add(z == 0)
        return z
    model.AddBoolOr(lits).OnlyEnforceIf(z)
    model.AddBoolAnd([l.Not() for l in lits]).OnlyEnforceIf(z.Not())
    return z


def slack_eccesso(model: cp_model.CpModel, espressione, soglia: int) -> cp_model.IntVar:
    """IntVar >= 0 che rappresenta max(0, espressione - soglia)."""
    eccesso = model.NewIntVar(0, 1000, f'eccesso_{next(_contatore)}')
    model.Add(eccesso >= espressione - soglia)
    return eccesso


def slack_deficit(model: cp_model.CpModel, espressione, soglia: int) -> cp_model.IntVar:
    """IntVar >= 0 che rappresenta max(0, soglia - espressione)."""
    deficit = model.NewIntVar(0, 1000, f'deficit_{next(_contatore)}')
    model.Add(deficit >= soglia - espressione)
    return deficit
