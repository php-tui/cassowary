<?php

declare(strict_types=1);

namespace PhpTui\Cassowary;

use RuntimeException;
use SplObjectStorage;
use Stringable;

class Solver implements Stringable
{
    /**
     * @param SplObjectStorage<Constraint,Tag> $constraints
     * @param SplObjectStorage<Symbol,Variable> $varForSymbol
     * @param SplObjectStorage<Variable,array{float, Symbol, int}> $varData
     * @param SplObjectStorage<Symbol,Row> $rows
     * @param SplObjectStorage<Variable,null> $changed
     * @param list<Symbol> $infeasibleRows
     * @param list<array{Variable,float}> $publicChanges
     */
    final private function __construct(
        public readonly SplObjectStorage $constraints,
        private SplObjectStorage $varForSymbol,
        private readonly SplObjectStorage $varData,
        private readonly SplObjectStorage $rows,
        private SplObjectStorage $changed,
        private readonly Row $objective,
        private ?Row $artificial,
        private int $idTick,
        private bool $shouldClearChanges = false,
        /** @phpstan-ignore-next-line Never read only written -- but maybe it would be if this port was finished */
        private array $infeasibleRows = [],
        private array $publicChanges = []
    ) {
    }

    public function __toString(): string
    {
        $string = [];
        foreach ($this->constraints as $constraint) {
            $string[] = $constraint->__toString();
        }

        return implode("\n", $string);
    }

    /**
     * @param Constraint[] $constraints
     */
    public function addConstraints(array $constraints): void
    {
        foreach ($constraints as $constraint) {
            $this->addConstraint($constraint);
        }
    }

    public static function new(): self
    {
        return new self(
            /** @phpstan-ignore-next-line */
            new SplObjectStorage(),
            /** @phpstan-ignore-next-line */
            new SplObjectStorage(),
            /** @phpstan-ignore-next-line */
            new SplObjectStorage(),
            /** @phpstan-ignore-next-line */
            new SplObjectStorage(),
            /** @phpstan-ignore-next-line */
            new SplObjectStorage(),
            Row::new(0.0),
            null,
            1,
        );
    }

    public function fetchChanges(): Changes
    {
        if ($this->shouldClearChanges) {
            $this->clearChanges();
        } else {
            $this->shouldClearChanges = true;
        }
        $this->publicChanges = [];
        foreach ($this->changed as $variable) {
            if ($this->varData->offsetExists($variable)) {
                $varData = $this->varData->offsetGet($variable);

                $newValue = $this->rows->offsetExists($varData[1]) ? $this->rows->offsetGet($varData[1])->constant : 0.0;

                $oldValue = $varData[0];
                if ($oldValue !== $newValue) {
                    $this->publicChanges[] = [$variable, $newValue];
                    $varData[0] = $newValue;
                    $this->varData->offsetSet($variable, $varData);
                }
            }
        }

        return new Changes($this->publicChanges);
    }

    public function addConstraint(Constraint $constraint): void
    {
        if ($this->constraints->offsetExists($constraint)) {
            throw new AddConstraintaintError(sprintf(
                'Constraint %s has already been added',
                $constraint->__toString()
            ));
        }

        // Creating a row causes symbols to reserved for the variables
        // in the constraint. If this method exits with an exception,
        // then its possible those variables will linger in the var map.
        // Since its likely that those variables will be used in other
        // constraints and since exceptional conditions are uncommon,
        // i'm not too worried about aggressive cleanup of the var map.
        [$row, $tag] = $this->createRow($constraint);

        $subject = Solver::chooseSubject($row, $tag);

        // If chooseSubject could not find a valid entering symbol, one
        // last option is available if the entire row is composed of
        // dummy variables. If the constant of the row is zero, then
        // this represents redundant constraints and the new dummy
        // marker can enter the basis. If the constant is non-zero,
        // then it represents an unsatisfiable constraint.
        if ($subject->symbolType === SymbolType::Invalid && $row->allDummies()) {
            if (false === SolverUtil::nearZero($row->constant)) {
                throw new AddConstraintaintError(sprintf(
                    'Unsatisfiable constraint: %s',
                    $constraint->__toString()
                ));
            }
            $subject = $tag->marker;
        }

        // If an entering symbol still isn't found, then the row must
        // be added using an artificial variable. If that fails, then
        // the row represents an unsatisfiable constraint.
        if ($subject->symbolType === SymbolType::Invalid) {
            if (!$this->addWithArtificialVariable($row)) {
                throw new AddConstraintaintError(sprintf(
                    'Could not add artificial variable for constraint: %s',
                    $constraint->__toString()
                ));
            }
        } else {
            $row->solveForSymbol($subject);
            $this->substitute($subject, $row);
            if ($subject->symbolType === SymbolType::External && $row->constant !== 0.0) {
                /** @var Variable $v */
                $v = $this->varForSymbol[$subject];
                $this->varChanged($v);
            }
            $this->rows->offsetSet($subject, $row);
        }

        $this->constraints->offsetSet($constraint, $tag);

        // Optimizing after each constraint is added performs less
        // aggregate work due to a smaller average system size. It
        // also ensures the solver remains in a consistent state.
        $this->optimise($this->objective);
    }

    /**
     * Create a new Row object for the given constraint.
     *
     * The terms in the constraint will be converted to cells in the row.
     * Any term in the constraint with a coefficient of zero is ignored.
     * This method uses the `getVarSymbol` method to get the symbol for
     * the variables added to the row. If the symbol for a given cell
     * variable is basic, the cell variable will be substituted with the
     * basic row.
     *
     * The necessary slack and error variables will be added to the row.
     * If the constant for the row is negative, the sign for the row
     * will be inverted so the constant becomes positive.
     *
     * The tag will be updated with the marker and error symbols to use
     * for tracking the movement of the constraint in the tableau.
     *
     * @return array{Row, Tag}
     */
    private function createRow(Constraint $constraint): array
    {
        $expr = $constraint->expression;
        $row = Row::new($expr->constant);

        // Substitute the current basic variables into the row.
        foreach ($expr->terms as $term) {
            if (SolverUtil::nearZero($term->coefficient)) {
                continue;
            }

            $symbol = $this->getVarSymbol($term->variable);

            if ($this->rows->offsetExists($symbol)) {
                $otherRow = $this->rows->offsetGet($symbol);
                $row->insertRow($otherRow, $term->coefficient);
            } else {
                $row->insertSymbol($symbol, $term->coefficient);
            }
        }

        $tag = (function () use ($constraint, $row): Tag {
            switch ($constraint->relationalOperator) {
                case RelationalOperator::GreaterThanOrEqualTo:
                case RelationalOperator::LessThanOrEqualTo:
                    $coefficient = $constraint->relationalOperator === RelationalOperator::LessThanOrEqualTo ? 1.0 : -1.0;
                    $slack = $this->spawnSymbol(SymbolType::Slack);
                    $row->insertSymbol($slack, $coefficient);
                    if ($constraint->strength < Strength::REQUIRED) {
                        $error = $this->spawnSymbol(SymbolType::Error);
                        $row->insertSymbol($error, -$coefficient);
                        $this->objective->insertSymbol($error, $constraint->strength);

                        return new Tag(
                            $slack,
                            $error
                        );
                    }

                    return new Tag(
                        $slack,
                        Symbol::invalid()
                    );
                case RelationalOperator::Equal:
                    if ($constraint->strength < Strength::REQUIRED) {
                        $errplus = $this->spawnSymbol(SymbolType::Error);
                        $errminus = $this->spawnSymbol(SymbolType::Error);
                        $row->insertSymbol($errplus, -1.0);
                        $row->insertSymbol($errminus, 1.0);

                        $this->objective->insertSymbol($errplus, $constraint->strength);
                        $this->objective->insertSymbol($errminus, $constraint->strength);

                        return new Tag(
                            $errplus,
                            $errminus
                        );
                    }

                    $dummy = $this->spawnSymbol(SymbolType::Dummy);
                    $row->insertSymbol($dummy, 1.0);

                    return new Tag(
                        $dummy,
                        Symbol::invalid()
                    );
                default:
                    throw new RuntimeException(sprintf('Cannot handle operator: %s', $constraint->relationalOperator->name));
            };
        })();

        if ($row->constant < 0.0) {
            $row->reverseSign();
        }

        return [$row, $tag];
    }

    /**
     * Get the symbol for the given variable.
     *
     * If a symbol does not exist for the variable, one will be created.
     */
    private function getVarSymbol(Variable $variable): Symbol
    {
        [$val, $symbol, $count] = (function () use ($variable) {
            if (false === $this->varData->offsetExists($variable)) {
                $symbol = $this->spawnSymbol(SymbolType::External);
                $this->varForSymbol->offsetSet($symbol, $variable);

                // TODO: use object here
                $data = [NAN, $symbol, 0];
                $this->varData->offsetSet($variable, $data);

                return $data;
            }

            return $this->varData->offsetGet($variable);
        })();

        $this->varData->offsetSet($variable, [
            $val,
            $symbol,
            ++$count,
        ]);

        return $symbol;
    }

    private function spawnSymbol(SymbolType $symbolType): Symbol
    {
        $this->idTick += 1;

        return new Symbol($this->idTick, $symbolType);
    }

    /**
     * Choose the subject for solving for the row.
     *
     * This method will choose the best subject for using as the solve
     * target for the row. An invalid symbol will be returned if there
     * is no valid target.
     *
     * The symbols are chosen according to the following precedence:
     *
     * 1) The first symbol representing an external variable.
     * 2) A negative slack or error tag variable.
     *
     * If a subject cannot be found, an invalid symbol will be returned.
     */
    private function chooseSubject(Row $row, Tag $tag): Symbol
    {
        foreach ($row->cells as $symbol) {
            if ($symbol->symbolType === SymbolType::External) {
                return $symbol;
            }
        }

        if ($tag->marker->symbolType === SymbolType::Slack || $tag->marker->symbolType === SymbolType::Error) {
            if ($row->coefficientFor($tag->marker) < 0.0) {
                return $tag->marker;
            }
        }

        if ($tag->other->symbolType === SymbolType::Slack || $tag->other->symbolType === SymbolType::Error) {
            if ($row->coefficientFor($tag->other) < 0.0) {
                return $tag->other;
            }
        }

        return Symbol::invalid();
    }

    private function addWithArtificialVariable(Row $row): bool
    {
        $artificialSymbol = $this->spawnSymbol(SymbolType::Slack);
        $this->rows->offsetSet($artificialSymbol, $row->clone());
        $this->artificial = $row->clone();
        // Optimize the artificial objective. This is successful
        // only if the artificial objective is optimized to zero.
        $this->optimise($this->artificial);

        /** @phpstan-ignore-next-line */
        $success = SolverUtil::nearZero($this->artificial->constant);
        $this->artificial = null;

        if ($this->rows->offsetExists($artificialSymbol)) {
            $row = $this->rows->offsetGet($artificialSymbol);
            $this->rows->offsetUnset($artificialSymbol);
            if ($row->cells->count() === 0) {
                return $success;
            }
            $entering = $row->anyPivoltableSymbol();
            if ($entering->symbolType === SymbolType::Invalid) {
                return false;
            }
            $row->solveForSymbols($artificialSymbol, $entering);
            $this->substitute($entering, $row);
            $this->rows->offsetSet($entering, $row);
        }

        foreach ($this->rows as $symbol) {
            $row = $this->rows->offsetGet($symbol);
            $row->remove($artificialSymbol);
        }
        $this->objective->remove($artificialSymbol);

        return $success;

    }

    /**
     * Optimize the system for the given objective function.
     *
     * This method performs iterations of Phase 2 of the simplex method
     * until the objective function reaches a minimum.
     */
    private function optimise(Row $objective): void
    {
        while (true) {
            $entering = $this->getEnteringSymbol($objective);
            if ($entering->symbolType === SymbolType::Invalid) {
                return;
            }

            [$leaving, $row] = $this->getLeavingRow($entering);

            // pivot the entering symbol into the basis
            $row->solveForSymbols($leaving, $entering);

            $this->substitute($entering, $row);
            if ($entering->symbolType === SymbolType::External && $row->constant != 0.0) {
                $v = $this->varForSymbol->offsetGet($entering);
                $this->varChanged($v);
            }
            $this->rows->offsetSet($entering, $row);
        }
    }

    /**
     * Compute the entering variable for a pivot operation.
     *
     * This method will return first symbol in the objective function which
     * is non-dummy and has a coefficient less than zero. If no symbol meets
     * the criteria, it means the objective function is at a minimum, and an
     * invalid symbol is returned.
     * Could return an External symbol
     */
    private function getEnteringSymbol(Row $objective): Symbol
    {
        foreach ($objective->cells as $symbol) {
            if ($symbol->symbolType !== SymbolType::Dummy) {
                if ($objective->cells->offsetGet($symbol) < 0.0) {
                    return $symbol;
                }
            }
        }

        return Symbol::invalid();
    }

    /**
     * Compute the row which holds the exit symbol for a pivot.
     *
     * This method will return an iterator to the row in the row map
     * which holds the exit symbol. If no appropriate exit symbol is
     * found, the end() iterator will be returned. This indicates that
     * the objective function is unbounded.
     * Never returns a row for an External symbol
     *
     * @return array{Symbol, Row}
     */
    private function getLeavingRow(Symbol $entering): array
    {
        $ratio = INF;
        $found = null;
        $foundRow = null;
        foreach ($this->rows as $symbol) {
            $row = $this->rows->offsetGet($symbol);

            if ($symbol->symbolType === SymbolType::External) {
                continue;
            }

            $temp = $row->coefficientFor($entering);
            if ($temp < 0.0) {
                $tempRatio = -$row->constant / $temp;
                if ($tempRatio < $ratio) {
                    $ratio = $tempRatio;
                    $found = $symbol;
                    $foundRow = $row;
                }
            }
        }

        if (null === $found || null === $foundRow) {
            throw new AddConstraintaintError(sprintf(
                'Could not find leaving row for entering symbol: %s',
                $entering->__toString()
            ));
        }

        $this->rows->offsetUnset($found);

        return [$found, $foundRow];
    }

    /**
     * Substitute the parametric symbol with the given row.
     *
     * This method will substitute all instances of the parametric symbol
     * in the tableau and the objective function with the given row.
     */
    private function substitute(Symbol $symbol, Row $row): void
    {
        foreach ($this->rows as $otherSymbol) {
            $otherRow = $this->rows->offsetGet($otherSymbol);
            $constantChanged = $otherRow->substitute($symbol, $row);

            if ($otherSymbol->symbolType === SymbolType::External && $constantChanged) {
                $this->varChanged($this->varForSymbol[$otherSymbol]);
            }
            if ($otherSymbol->symbolType !== SymbolType::External && $otherRow->constant < 0.0) {
                $this->infeasibleRows[] = $otherSymbol;
            }
        }
        $this->objective->substitute($symbol, $row);
        if ($this->artificial !== null) {
            $this->artificial->substitute($symbol, $row);
        }
    }

    private function varChanged(Variable $variable): void
    {
        if ($this->shouldClearChanges) {
            $this->clearChanges();
        }
        $this->changed->offsetSet($variable);
    }

    private function clearChanges(): void
    {
        $this->changed = new SplObjectStorage();
        $this->shouldClearChanges = false;
    }
}
