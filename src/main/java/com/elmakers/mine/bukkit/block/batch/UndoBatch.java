package com.elmakers.mine.bukkit.block.batch;

import java.util.Set;

import org.apache.commons.lang.ArrayUtils;
import org.bukkit.Material;
import org.bukkit.block.Block;

import com.elmakers.mine.bukkit.api.block.BlockBatch;
import com.elmakers.mine.bukkit.api.block.BlockData;
import com.elmakers.mine.bukkit.api.magic.Mage;
import com.elmakers.mine.bukkit.api.magic.MageController;
import com.elmakers.mine.bukkit.block.UndoList;

public class UndoBatch implements com.elmakers.mine.bukkit.api.block.UndoBatch {
    protected final MageController controller;
    private UndoList trackUndoBlocks;
    private static final BlockData[] template = new BlockData[0];
    private final BlockData[] undoBlocks;
    private int undoIndex = 0;
    private boolean finishedAttachables = false;
    protected boolean finished = false;
    protected boolean applyPhysics = false;
    protected UndoList undoList;

    private final Set<Material> attachables;
    private final Set<Material> attachablesWall;
    private final Set<Material> attachablesDouble;
    private final Set<Material> delayed;

    public UndoBatch(UndoList blockList) {
        Mage mage = blockList.getOwner();
        controller = mage.getController();

        // We're going to track the blocks we undo
        // But this doens't get put back in the undo queue, or
        // it will just flip-flop forever between these two actions.
        // Maybe eventually we'll have a "redo" queue.
        trackUndoBlocks = new UndoList(mage, blockList.getSpell(), "Undo");
        trackUndoBlocks.setBypass(true);

        undoList = blockList;
        this.applyPhysics = blockList.getApplyPhysics();
        this.undoBlocks = blockList.toArray(template);
        ArrayUtils.reverse(this.undoBlocks);
        this.attachables = controller.getMaterialSet("attachable");
        this.attachablesWall = controller.getMaterialSet("attachable_wall");
        this.attachablesDouble = controller.getMaterialSet("attachable_double");
        this.delayed = controller.getMaterialSet("delayed");
    }

    public int size() {
        return undoBlocks == null ? 0 : undoBlocks.length;
    }

    public int remaining() {
        return undoBlocks == null ? 0 : undoBlocks.length - undoIndex;
    }

    public int process(int maxBlocks) {
        int processedBlocks = 0;
        while (undoBlocks != null && undoIndex < undoBlocks.length && processedBlocks < maxBlocks) {
            BlockData blockData = undoBlocks[undoIndex];
            Block block = blockData.getBlock();
            if (!block.getChunk().isLoaded()) {
                block.getChunk().load();
                break;
            }
            Material material = block.getType();
            boolean isAttachable = attachables.contains(material) || attachablesWall.contains(material)
                    || attachablesDouble.contains(material) || delayed.contains(material);
            if ((isAttachable && !finishedAttachables) || (!isAttachable && finishedAttachables)) {

                trackUndoBlocks.add(blockData);

                if (!UndoList.undo(blockData, applyPhysics)) {
                    break;
                }
            }
            undoIndex++;
            processedBlocks++;
        }
        if (undoBlocks == null || (undoIndex >= undoBlocks.length && finishedAttachables)) {
            finish();
        } else if (undoIndex >= undoBlocks.length) {
            finishedAttachables = true;
            undoIndex = 0;
        }

        return processedBlocks;
    }

    public void finish() {
        if (!finished) {
            undoList.undoEntityEffects();
            finished = true;
            controller.update(trackUndoBlocks);
        }
    }

    @Override
    public boolean isFinished() {
        return finished;
    }
}
