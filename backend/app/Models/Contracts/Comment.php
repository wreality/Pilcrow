<?php
declare(strict_types=1);

namespace App\Models\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A submission comment — inline or overall, top-level or reply (replies are
 * same-table rows). The common type {@see \App\Models\InlineComment} and
 * {@see \App\Models\OverallComment} share so authorization can treat them
 * uniformly: a comment-scoped {@see \App\Auth\Abilities\CommentAbility},
 * resolved through {@see \App\Auth\ScopedAbilityResolver} with the
 * {@see \App\Auth\Grants\Predicates\OwnsCommentWhileReviewable} predicate, covers
 * both — keyed off authorship (`created_by`) and the owning submission.
 *
 * @property int|null $created_by The author's user id.
 */
interface Comment
{
    /**
     * The submission the comment belongs to — the entity whose roles and
     * reviewable status the comment's authorization is resolved against.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function submission(): BelongsTo;
}
